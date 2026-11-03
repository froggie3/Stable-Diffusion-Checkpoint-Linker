<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Config;

use InvalidArgumentException;

final class ApplicationSettingsLoader
{
    public function load(string $projectRoot): ApplicationSettings
    {
        $path = rtrim($projectRoot, '/') . '/setting.ini';
        $values = [];
        if (file_exists($path) || is_link($path)) {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                throw new InvalidArgumentException("failed to read settings: $path");
            }
            $values = @parse_ini_string($contents, true, INI_SCANNER_RAW);
            if ($values === false) {
                throw new InvalidArgumentException("invalid INI settings: $path");
            }
        }

        foreach ($values as $key => $value) {
            if (!in_array($key, ['configuration_dir', 'database_path'], true)) {
                throw new InvalidArgumentException("unknown setting: $key");
            }
            if (!is_string($value) || trim($value) === '' || str_contains($value, "\0")) {
                throw new InvalidArgumentException("setting $key must be a non-empty path.");
            }
        }

        return new ApplicationSettings(
            $this->resolvePath($projectRoot, $values['configuration_dir'] ?? 'config'),
            $this->resolvePath($projectRoot, $values['database_path'] ?? 'data/links.sqlite')
        );
    }

    private function resolvePath(string $projectRoot, string $path): string
    {
        return str_starts_with($path, '/') ? $path : rtrim($projectRoot, '/') . '/' . $path;
    }
}
