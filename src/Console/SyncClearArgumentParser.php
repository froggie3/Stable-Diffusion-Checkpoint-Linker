<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

final class SyncClearArgumentParser
{
    public function parse(array $arguments): CliOptions
    {
        $help = false;
        $dryRun = false;
        $verbose = false;
        $categories = null;
        $configFile = null;
        $endOfOptions = false;
        for ($i = 0; $i < count($arguments); ++$i) {
            $argument = $arguments[$i];
            if ($endOfOptions || $argument === '--' || !str_starts_with($argument, '-')) {
                if ($argument === '--' && !$endOfOptions) {
                    $endOfOptions = true;
                    continue;
                }
                if ($configFile !== null) {
                    return new CliOptions(error: "unexpected positional argument: $argument");
                }
                if ($argument === '') {
                    return new CliOptions(error: 'configuration file path must not be empty');
                }
                $configFile = $argument;
                continue;
            }
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
        return new CliOptions($help, $dryRun, $verbose, $categories, configFile: $configFile);
    }

}
