<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

use Psr\Log\LoggerInterface;

final class EditCommand
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        if (count($arguments) === 1 && ($arguments[0] === '--help' || $arguments[0] === '-h')) {
            (new HelpPrinter())->printEditHelp();
            return ExitCode::SUCCESS;
        }
        if ($arguments !== [] && str_starts_with($arguments[0], '-')) {
            $message = in_array($arguments[0], ['--help', '-h'], true)
                ? 'edit help does not accept extra arguments'
                : "unknown option: {$arguments[0]}";
            $this->logger->error('argument error: {message}', ['message' => $message]);
            return ExitCode::INVALID_ARGUMENT;
        }
        if ($arguments === [] || $arguments[0] === '') {
            $this->logger->error('argument error: edit requires a configuration file path');
            return ExitCode::INVALID_ARGUMENT;
        }

        $configFile = array_shift($arguments);
        if ($arguments !== []) {
            $editor = array_shift($arguments);
            if ($editor === '') {
                $this->logger->error('argument error: editor command must not be empty');
                return ExitCode::INVALID_ARGUMENT;
            }
            $command = [$editor, ...$arguments, $configFile];
        } else {
            $editor = getenv('VISUAL');
            if ($editor === false || trim($editor) === '') {
                $editor = getenv('EDITOR');
            }
            if ($editor === false || trim($editor) === '') {
                $this->logger->error('argument error: no editor specified and VISUAL and EDITOR are unset');
                return ExitCode::INVALID_ARGUMENT;
            }
            $command = ['/bin/sh', '-c', $editor . ' "$@"', 'sd-linker-edit', $configFile];
        }

        $process = @proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
        if (!is_resource($process)) {
            $this->logger->error('editor failed to start');
            return ExitCode::FAILURE;
        }
        $status = proc_close($process);
        if ($status !== 0) {
            $this->logger->error('editor exited with status {status}', ['status' => $status]);
            return ExitCode::FAILURE;
        }
        return ExitCode::SUCCESS;
    }
}
