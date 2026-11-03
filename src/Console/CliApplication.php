<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

use Monolog\Handler\StreamHandler;
use Monolog\Handler\FilterHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

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
            (new HelpPrinter())->printGlobalHelp();
            return ExitCode::SUCCESS;
        }

        $command = Command::tryFrom($arguments[0]);
        if ($command === null) {
            $this->logger->error('argument error: unknown command: {command}', ['command' => $arguments[0]]);
            return ExitCode::INVALID_ARGUMENT;
        }

        $arguments = array_slice($arguments, 1);
        return match ($command) {
            Command::SYNC, Command::CLEAR => (new SyncClearCommand($this->projectRoot, $this->logger))->run($command, $arguments),
            Command::EDIT => (new EditCommand($this->logger))->run($arguments),
            Command::LIST => (new ListCommand($this->projectRoot, $this->logger))->run($arguments),
        };
    }
}
