<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

use Iigau\StableDiffusionCheckpointLinker\Config\Configuration;
use Iigau\StableDiffusionCheckpointLinker\Config\ConfigurationLoader;
use Iigau\StableDiffusionCheckpointLinker\Link\Linker;
use Iigau\StableDiffusionCheckpointLinker\Link\PathIdentity;
use Iigau\StableDiffusionCheckpointLinker\Record\SQLiteRecordRepository;
use Iigau\StableDiffusionCheckpointLinker\Sync\OperationExecutor;
use Iigau\StableDiffusionCheckpointLinker\Sync\OperationPlanner;
use Iigau\StableDiffusionCheckpointLinker\Sync\OperationType;
use Iigau\StableDiffusionCheckpointLinker\Sync\Synchronizer;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FilterHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;
use Throwable;

final class CliApplication
{
    private LoggerInterface $logger;

    public function __construct(private string $projectRoot, ?LoggerInterface $logger = null)
    {
        if ($logger !== null) {
            $this->logger = $logger;
            return;
        }
        $logger = new Logger('sd-linker');
        $stdout = new StreamHandler(STDOUT);
        $stdout->setFormatter(new LineFormatter("%message%\n", null, true, true));
        $stderr = new StreamHandler(STDERR, Level::Warning);
        $stderr->setFormatter(new LineFormatter("%message%\n", null, true, true));
        $logger->pushHandler(new FilterHandler($stdout, Level::Info, Level::Info));
        $logger->pushHandler($stderr);
        $logger->pushProcessor(new PsrLogMessageProcessor());
        $this->logger = $logger;
    }

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        if ($arguments === [] || (count($arguments) === 1 && ($arguments[0] === '-h' || $arguments[0] === '--help'))) {
            $this->printGlobalHelp();
            return ExitCode::SUCCESS;
        }

        $command = Command::tryFrom($arguments[0]);
        if ($command === null) {
            $this->logger->error('argument error: unknown command: {command}', ['command' => $arguments[0]]);
            return ExitCode::INVALID_ARGUMENT;
        }

        $arguments = array_slice($arguments, 1);
        $options = $this->parseArguments($arguments);
        if ($options->error !== null) {
            $this->logger->error('argument error: {message}', ['message' => $options->error]);
            return ExitCode::INVALID_ARGUMENT;
        }
        if ($options->help) {
            $this->printHelp($command);
            return ExitCode::SUCCESS;
        }

        try {
            $input = file_get_contents('php://stdin');
            if ($input === false) {
                throw new \InvalidArgumentException('failed to read configuration from stdin.');
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
            $repository = new SQLiteRecordRepository($this->projectRoot . '/data/links.sqlite');
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

    private function parseArguments(array $arguments): CliOptions
    {
        $help = false;
        $dryRun = false;
        $verbose = false;
        $categories = null;
        for ($i = 0; $i < count($arguments); ++$i) {
            $argument = $arguments[$i];
            if ($argument === '--help' || $argument === '-h') {
                $help = true;
            } elseif ($argument === '--dry-run') {
                $dryRun = true;
            } elseif ($argument === '--verbose' || $argument === '-v') {
                $verbose = true;
            } elseif ($argument === '--category') {
                $category = $arguments[++$i] ?? '';
                if ($category === '' || str_starts_with($category, '--')) {
                    return new CliOptions(error: '--category requires a category name');
                }
                $categories ??= [];
                $categories[] = $category;
            } elseif (str_starts_with($argument, '--category=')) {
                $category = substr($argument, strlen('--category='));
                if ($category === '') {
                    return new CliOptions(error: '--category requires a category name');
                }
                $categories ??= [];
                $categories[] = $category;
            } else {
                return new CliOptions(error: "unknown option: $argument");
            }
        }
        return new CliOptions($help, $dryRun, $verbose, $categories);
    }

    private function printGlobalHelp(): void
    {
        $output = new ConsoleOutput();
        foreach ([
            'Usage: ./linker <command> [OPTIONS] < config.yaml',
            '',
            'Commands:',
            '  sync     ' . Command::SYNC->description(),
            '  clear    ' . Command::CLEAR->description(),
            '',
            'Run ./linker <command> --help for configuration, options, safety, and output details.',
            'Configuration is read from standard input. Help does not read stdin or open the database.',
        ] as $line) {
            $output->line($line);
        }
    }

    private function printHelp(Command $command): void
    {
        $output = new ConsoleOutput();
        $modeAction = $command === Command::CLEAR
            ? 'Remove only links that the management database confirms are managed and that this configuration lists.'
            : 'Create, repair, replace, register, or remove the links selected by this configuration.';
        $lines = [
            "Usage: ./linker {$command->value} [OPTIONS] < config.yaml",
            '',
            $modeAction,
            'Configuration is read from standard input. --help does not read stdin or open the database.',
            '',
            'Options:',
            '  -h, --help                 show this help.',
            '  --dry-run                  validate and print planned actions; make no changes.',
            '  -v, --verbose              print successful operation details and counts to stdout.',
            '  --category NAME            limit to a lookupKeys category (repeatable; --category=NAME is also accepted).',
            '',
            'Examples:',
            "  ./linker {$command->value} --help",
            "  ./linker {$command->value} --dry-run < config.yaml",
            "  ./linker {$command->value} -v --category checkpoint < config.yaml",
            '',
            'Minimal YAML configuration:',
            '  lookupKeys: [checkpoint]',
            '  source:',
            '    checkpoint: []',
            '  destination:',
            '    checkpoint: /srv/models/checkpoint',
            '',
            'Configuration keys and types:',
            '  lookupKeys: required non-empty list of unique category strings; controls category order.',
            '  source: required mapping; each listed category maps to a list of model-group mappings (empty allowed).',
            '  destination: required mapping; each listed category maps to a safe absolute path.',
            '  source.<category>.<index>.meta.enabled: required boolean.',
            '  source.<category>.<index>.baseDirectory: required safe absolute path.',
            '  source.<category>.<index>.weightsList: required list of relative path strings; empty allowed.',
            '  source.<category>.<index>.ignoreList: optional list of relative path strings; defaults to empty.',
            '  All categories in lookupKeys are schema-validated, including categories outside --category.',
            '  --category only chooses planned categories and preserves lookupKeys order.',
            '',
            'Selection behavior:',
            '  Enabled groups: sync keeps weightsList links and treats ignoreList as removal candidates.',
            '  Disabled groups: sync treats weightsList as removal candidates; ignoreList has no sync effect.',
            '  clear considers both weightsList and ignoreList for enabled and disabled groups.',
            '  Entries removed from YAML are not automatically pruned.',
            '',
            'Paths and safety:',
            '  Paths are literal; no tilde, environment-variable, or glob expansion occurs.',
            '  baseDirectory and destination paths must be absolute; list entries must be relative and contain no empty, dot, or .. components.',
            '  Regular files/directories are protected. Sync can register an unmanaged symlink only when it already points to the configured source; other unmanaged links and manually changed managed links are rejected.',
            '  Preflight validates the full selected plan before any changes. Preflight failure makes no changes.',
            '  Execution stops at the first failure; completed work remains and is not rolled back.',
            '  Failure diagnostics identify completed operations, partial effects, the failed target, and operations not run.',
            '',
            'Output and status:',
            '  Successful execution is silent by default; verbose success details and counts go to stdout.',
            '  Errors and failure reports go to stderr. Dry-run plans and help always go to stdout.',
            '  Dry-run actions: create makes a missing link; repair recreates a missing managed link; replace redirects a managed link; register records a matching unmanaged link; unlink removes a managed link and record; delete_record removes a stale record for a missing link; noop leaves a matching target unchanged.',
            '  Verbose counts are created, repaired, replaced, registered, removed, unchanged, and operations completed. Removed includes unlink and delete_record actions.',
            '  Exit codes: 0 success, 1 operation/configuration failure, 2 invalid arguments.',
            '  Managed-link records are stored in data/links.sqlite under the project checkout.',
        ];
        foreach ($lines as $line) {
            $output->line($line);
        }
    }
}
