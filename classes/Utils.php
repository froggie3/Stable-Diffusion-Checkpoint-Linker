<?php

declare(strict_types=1);

class Utils
{
    protected const ERROR_INVALID_JSON_PATH = 'Invalid path for JSON';
    protected const ERROR_INVALID_JSON = 'Invalid JSON was detected, check if it is valid.';

    /**
     * Join string into a single URL string.
     *
     * @param string $parts,... The parts of the URL to join.
     * @return string The URL string.
     */
    static function joinPaths(string ...$parts): string
    {
        if (sizeof($parts) === 0) {
            return '';
        }

        $prefix = ($parts[0] === DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : '';
        $processed = array_filter(
            array_map(fn ($part) => rtrim($part, DIRECTORY_SEPARATOR), $parts),
            fn ($part) => !empty($part)
        );

        return $prefix . implode(DIRECTORY_SEPARATOR, $processed);
    }

    /**
     * Returns actual value when the value of argument exists
     * otherwise returns false
     */
    static function resolveArgumentValue(string $argument, array $argumentList): string
    {
        try {
            if (array_key_exists($argument, $argumentList)) {
                return $argumentList[$argument];
            }
            throw new Exception("Lack of mandatory arguments: $argument");
        } catch (Exception $e) {
            // echo "Usage: add --json PATH to specify a config file\n";
            echo $e->getMessage() . PHP_EOL;
            return "";
        }
    }

    /**
     * Imports parameters from JSON
     *
     * @return array the associated array converted or parsed from JSON
     */
    static function configVariablesImport($jsonPath): array
    {
        $params = [];

        // try reading the path from stdin.
        if (!$jsonPath) {
            echo "input your path to config file (Ctrl-C or EOF to abort): ";
            if (is_string($line = fgets(STDIN))) {
                $jsonPath = trim($line);
            } else { // bool
                return [];
            }
        }

        // Check if .json is available but otherwise exit
        try {
            if (!($jsonPath && file_exists($jsonPath))) {
                throw new Exception(self::ERROR_INVALID_JSON_PATH);
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            return [];
        }

        // Check if .json is valid but otherwise exit
        try {
            if (!$params = json_decode(file_get_contents($jsonPath), true)) {
                throw new Exception(self::ERROR_INVALID_JSON);
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            return [];
        }

        return $params;
    }
}
