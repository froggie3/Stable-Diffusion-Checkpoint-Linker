<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

use Iigau\StableDiffusionCheckpointLinker\Config\ConfigurationLoader;
use Iigau\StableDiffusionCheckpointLinker\Link\Linker;
use Iigau\StableDiffusionCheckpointLinker\Link\PathIdentity;
use Iigau\StableDiffusionCheckpointLinker\Record\SQLiteRecordRepository;
use Iigau\StableDiffusionCheckpointLinker\Sync\OperationExecutor;
use Iigau\StableDiffusionCheckpointLinker\Sync\OperationPlanner;
use Iigau\StableDiffusionCheckpointLinker\Sync\Synchronizer;
use Throwable;

final class CliApplication
{
    public function __construct(private string $projectRoot)
    {
    }

    public function run(string $mode, array $arguments): int
    {
        $options = $this->parseArguments($arguments);
        if (isset($options['error'])) {
            fwrite(STDERR, "Argument error: {$options['error']}\n");
            return 2;
        }
        if (($options['help'] ?? false) === true) {
            $this->printHelp($mode);
            return 0;
        }

        try {
            $input = file_get_contents('php://stdin');
            if ($input === false) {
                throw new \InvalidArgumentException('Failed to read configuration from stdin.');
            }
            $config = (new ConfigurationLoader())->load($input);
        } catch (\InvalidArgumentException $e) {
            fwrite(STDERR, 'Configuration error: ' . $e->getMessage() . PHP_EOL);
            return 1;
        }

        $selected = $config['lookupKeys'];
        if ($options['categories'] !== null) {
            foreach ($options['categories'] as $category) {
                if (!in_array($category, $config['lookupKeys'], true)) {
                    fwrite(STDERR, "Argument error: category is outside lookupKeys: $category\n");
                    return 2;
                }
            }
            $requested = array_fill_keys($options['categories'], true);
            $selected = array_values(array_filter(
                $config['lookupKeys'],
                static fn (string $category): bool => isset($requested[$category])
            ));
        }

        try {
            $repository = new SQLiteRecordRepository($this->projectRoot . '/data/links.sqlite');
            $planner = new OperationPlanner(
                $selected,
                $config['source'],
                $config['destination'],
                new Linker(),
                $repository,
                new PathIdentity()
            );
            $plan = $mode === 'clear' ? $planner->planClear() : $planner->planSync();
        } catch (Throwable $e) {
            fwrite(STDERR, 'Preflight error: ' . $e->getMessage() . PHP_EOL);
            return 1;
        }

        if (!$plan->isValid()) {
            foreach ($plan->errors as $error) {
                fwrite(STDERR, "Preflight error: $error\n");
            }
            return 1;
        }

        if ($options['dryRun']) {
            foreach ($plan->operations as $operation) {
                fwrite(STDOUT, 'Would ' . $operation->description() . PHP_EOL);
            }
            fwrite(STDOUT, 'Dry run: no filesystem or database changes made.' . PHP_EOL);
            return 0;
        }

        try {
            $repository->activateForWrites();
        } catch (Throwable $e) {
            fwrite(STDERR, 'Execution initialization failed: ' . $e->getMessage() . PHP_EOL);
            foreach ($plan->operations as $operation) {
                fwrite(STDERR, 'Not executed: ' . $operation->description() . PHP_EOL);
            }
            return 1;
        }

        $executor = new OperationExecutor(new Linker(), $repository, new PathIdentity());
        $result = (new Synchronizer($plan, $executor))->run();
        (new SyncResultPrinter())->print($result);
        return $result->isSuccessful() ? 0 : 1;
    }

    private function parseArguments(array $arguments): array
    {
        $options = ['help' => false, 'dryRun' => false, 'categories' => null];
        for ($i = 0; $i < count($arguments); ++$i) {
            $argument = $arguments[$i];
            if ($argument === '--help' || $argument === '-h') {
                $options['help'] = true;
            } elseif ($argument === '--dry-run') {
                $options['dryRun'] = true;
            } elseif ($argument === '--category') {
                $category = $arguments[++$i] ?? '';
                if ($category === '' || str_starts_with($category, '--')) {
                    return ['error' => '--category requires a category name'];
                }
                $options['categories'] ??= [];
                $options['categories'][] = $category;
            } elseif (str_starts_with($argument, '--category=')) {
                $category = substr($argument, strlen('--category='));
                if ($category === '') {
                    return ['error' => '--category requires a category name'];
                }
                $options['categories'] ??= [];
                $options['categories'][] = $category;
            } else {
                return ['error' => "unknown option: $argument"];
            }
        }
        return $options;
    }

    private function printHelp(string $mode): void
    {
        fwrite(STDOUT, "Usage: php bin/$mode [--help] [--dry-run] [--category NAME]...\n");
        fwrite(STDOUT, "Configuration is read from standard input.\n");
    }
}
