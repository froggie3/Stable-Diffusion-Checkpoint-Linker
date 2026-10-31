<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Config;

use InvalidArgumentException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class ConfigurationLoader
{
    public function load(string $yaml): Configuration
    {
        try {
            $config = Yaml::parse($yaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $e) {
            throw new InvalidArgumentException('invalid YAML: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($config) || array_is_list($config)) {
            throw new InvalidArgumentException('configuration must be a YAML mapping.');
        }

        $keys = $config['lookupKeys'] ?? null;
        if (!is_array($keys) || !array_is_list($keys) || $keys === []) {
            throw new InvalidArgumentException('lookupKeys must be a non-empty list of category names.');
        }

        foreach (['source', 'destination'] as $section) {
            if (!isset($config[$section]) || !is_array($config[$section])) {
                throw new InvalidArgumentException("$section must be a YAML mapping.");
            }
        }

        $seen = [];
        foreach ($keys as $key) {
            if (!is_string($key) || trim($key) === '') {
                throw new InvalidArgumentException('lookupKeys entries must be non-empty strings.');
            }
            if (isset($seen[$key])) {
                throw new InvalidArgumentException("duplicate category in lookupKeys: $key");
            }
            $seen[$key] = true;

            if (!array_key_exists($key, $config['source']) ||
                !is_array($config['source'][$key]) || !array_is_list($config['source'][$key])) {
                throw new InvalidArgumentException("source.$key must be a list of model groups (an empty list is allowed).");
            }

            foreach ($config['source'][$key] as $index => $group) {
                $this->validateGroup($key, $index, $group);
            }

            $destination = $config['destination'][$key] ?? null;
            if (!$this->isSafeAbsolutePath($destination)) {
                throw new InvalidArgumentException("destination.$key must be a safe absolute path.");
            }
        }

        $categories = [];
        foreach ($keys as $key) {
            $groups = [];
            foreach ($config['source'][$key] as $group) {
                $groups[] = new ModelGroup(
                    $group['meta']['enabled'],
                    $group['baseDirectory'],
                    $group['weightsList'],
                    $group['ignoreList'] ?? []
                );
            }
            $categories[$key] = new CategoryConfiguration($key, $config['destination'][$key], $groups);
        }
        return new Configuration($keys, $categories);
    }

    private function validateGroup(string $category, int $index, mixed $group): void
    {
        $path = "source.$category.$index";
        if (!is_array($group) || array_is_list($group)) {
            throw new InvalidArgumentException("$path must be a mapping.");
        }

        $meta = $group['meta'] ?? null;
        if (!is_array($meta) || !array_key_exists('enabled', $meta) || !is_bool($meta['enabled'])) {
            throw new InvalidArgumentException("$path.meta.enabled must be a boolean.");
        }

        if (!isset($group['baseDirectory']) || !$this->isSafeAbsolutePath($group['baseDirectory'])) {
            throw new InvalidArgumentException("$path.baseDirectory must be a safe absolute path.");
        }

        $this->validateStringList($group['weightsList'] ?? null, "$path.weightsList", true);
        if (array_key_exists('ignoreList', $group)) {
            $this->validateStringList($group['ignoreList'], "$path.ignoreList", true);
        }
    }

    private function validateStringList(mixed $value, string $path, bool $relativePaths = false): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException("$path must be a list of strings.");
        }
        foreach ($value as $index => $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException("$path.$index must be a non-empty string.");
            }
            if (str_contains($item, "\0")) {
                throw new InvalidArgumentException("$path.$index must not contain a NUL byte.");
            }
            if ($relativePaths && (str_starts_with($item, '/') || array_intersect(explode('/', $item), ['', '.', '..']) !== [])) {
                throw new InvalidArgumentException("$path.$index must be a safe relative path without empty, dot, or '..' components.");
            }
        }
    }

    private function isSafeAbsolutePath(mixed $value): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && str_starts_with($value, '/')
            && !str_contains($value, "\0")
            && !in_array('..', explode('/', $value), true);
    }
}
