<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

use Iigau\StableDiffusionCheckpointLinker\Config\ApplicationSettingsLoader;
use Psr\Log\LoggerInterface;
use Throwable;

final class ListCommand
{
    public function __construct(private string $projectRoot, private LoggerInterface $logger)
    {
    }

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        if (count($arguments) === 1 && in_array($arguments[0], ['--help', '-h'], true)) {
            (new HelpPrinter())->printListHelp();
            return ExitCode::SUCCESS;
        }
        if ($arguments !== []) {
            $this->logger->error('argument error: unexpected argument: {argument}', ['argument' => $arguments[0]]);
            return ExitCode::INVALID_ARGUMENT;
        }

        try {
            $directory = (new ApplicationSettingsLoader())->load($this->projectRoot)->configDirectory;
            if (!is_dir($directory) || !is_readable($directory)) {
                throw new \RuntimeException("configuration directory is not readable: $directory");
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );
            $paths = [];
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), ['yaml', 'yml'], true)) {
                    $paths[] = $file->getPathname();
                }
            }
            sort($paths, SORT_STRING);
            $output = new ConsoleOutput();
            foreach ($paths as $path) {
                $output->line($path);
            }
            return ExitCode::SUCCESS;
        } catch (Throwable $e) {
            $this->logger->error('list error: {message}', ['message' => $e->getMessage()]);
            return ExitCode::FAILURE;
        }
    }
}
