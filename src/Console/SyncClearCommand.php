<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

use Iigau\StableDiffusionCheckpointLinker\Config\ApplicationSettingsLoader;
use Iigau\StableDiffusionCheckpointLinker\Config\ConfigurationLoader;
use Iigau\StableDiffusionCheckpointLinker\Link\Linker;
use Iigau\StableDiffusionCheckpointLinker\Link\PathIdentity;
use Iigau\StableDiffusionCheckpointLinker\Record\SQLiteRecordRepository;
use Iigau\StableDiffusionCheckpointLinker\Sync\OperationExecutor;
use Iigau\StableDiffusionCheckpointLinker\Sync\OperationPlanner;
use Iigau\StableDiffusionCheckpointLinker\Sync\Synchronizer;
use Psr\Log\LoggerInterface;
use Throwable;

final class SyncClearCommand
{
    public function __construct(private string $projectRoot, private LoggerInterface $logger)
    {
    }

    /**
     * @param Command::SYNC|Command::CLEAR $command
     * @param list<string> $arguments
     */
    public function run(Command $command, array $arguments): int
    {
        $options = (new SyncClearArgumentParser())->parse($arguments);
        if ($options->error !== null) {
            $this->logger->error('argument error: {message}', ['message' => $options->error]);
            return ExitCode::INVALID_ARGUMENT;
        }
        if ($options->help) {
            (new HelpPrinter())->printHelp($command);
            return ExitCode::SUCCESS;
        }

        try {
            $settings = (new ApplicationSettingsLoader())->load($this->projectRoot);
            $input = $options->configFile === null
                ? file_get_contents('php://stdin')
                : @file_get_contents($options->configFile);
            if ($input === false) {
                $source = $options->configFile === null ? 'stdin' : $options->configFile;
                throw new \InvalidArgumentException("failed to read configuration from $source.");
            }
            $config = (new ConfigurationLoader())->load($input);
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('configuration error: {message}', ['message' => $e->getMessage()]);
            return ExitCode::FAILURE;
        }

        $selected = $config->lookupKeys;
        if ($options->categories !== null) {
            foreach ($options->categories as $category) {
                if (!in_array($category, $config->lookupKeys, true)) {
                    $this->logger->error('argument error: category is outside lookupKeys: {category}', ['category' => $category]);
                    return ExitCode::INVALID_ARGUMENT;
                }
            }
            $requested = array_fill_keys($options->categories, true);
            $selected = array_values(array_filter(
                $config->lookupKeys,
                static fn (string $category): bool => isset($requested[$category])
            ));
        }

        try {
            $repository = new SQLiteRecordRepository($settings->databasePath);
            $planner = new OperationPlanner(
                $config->categories(),
                $selected,
                new Linker(),
                $repository,
                new PathIdentity()
            );
            $plan = $command === Command::CLEAR ? $planner->planClear() : $planner->planSync();
        } catch (Throwable $e) {
            $this->logger->error('preflight error: {message}', ['message' => $e->getMessage()]);
            return ExitCode::FAILURE;
        }

        if (!$plan->isValid()) {
            foreach ($plan->errors as $error) {
                $this->logger->error('preflight error: {message}', ['message' => $error]);
            }
            return ExitCode::FAILURE;
        }

        if ($options->dryRun) {
            $output = new ConsoleOutput();
            foreach ($plan->operations as $operation) {
                $output->line('would ' . $operation->description());
            }
            $output->line('dry run: no filesystem or database changes made.');
            return ExitCode::SUCCESS;
        }

        try {
            $repository->activateForWrites();
        } catch (Throwable $e) {
            $this->logger->error('execution initialization failed: {message}', ['message' => $e->getMessage()]);
            foreach ($plan->operations as $operation) {
                $this->logger->error('not executed: {operation}', ['operation' => $operation->description()]);
            }
            return ExitCode::FAILURE;
        }

        $executor = new OperationExecutor(new Linker(), $repository, new PathIdentity());
        $result = (new Synchronizer($plan, $executor))->run();
        (new SyncResultPrinter($this->logger))->print($result, $options->verbose);
        return $result->isSuccessful() ? ExitCode::SUCCESS : ExitCode::FAILURE;
    }
}
