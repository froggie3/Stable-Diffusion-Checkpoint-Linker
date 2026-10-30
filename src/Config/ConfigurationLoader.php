<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Config;

use InvalidArgumentException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class ConfigurationLoader
{
    public function load(string $yaml): array
    {
        try {
            $config = Yaml::parse($yaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $e) {
            throw new InvalidArgumentException('Invalid YAML: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($config) || array_is_list($config)) {
            throw new InvalidArgumentException('Configuration must be a YAML mapping.');
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
                throw new InvalidArgumentException("Duplicate category in lookupKeys: $key");
            }
            $seen[$key] = true;

            if (!array_key_exists($key, $config['source']) ||
                !is_array($config['source'][$key]) || !array_is_list($config['source'][$key])) {
                throw new InvalidArgumentException("source.$key must be a list of model groups (an empty list is allowed).");
            }

            $destination = $config['destination'][$key] ?? null;
            if (!is_string($destination) || trim($destination) === '') {
                throw new InvalidArgumentException("destination.$key must be a non-empty path string.");
            }
        }

        return $config;
    }
}
