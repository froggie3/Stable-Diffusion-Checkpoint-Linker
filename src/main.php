#!/usr/bin/php
<?php

declare(strict_types=1);
include 'classes/Autoloader.php';

$definedCmds = ['newConfig' => 'newconfig'];
$hasNewConfig = in_array($definedCmds['newConfig'], $argv) ?: false;

if ($hasNewConfig) {
    (new NewConfig($definedCmds['newConfig'], $argv))->run();
} else {
    (new Linker)->run();
}
    
/**
 * Join string into a single URL string.
 *
 * @param string $parts,... The parts of the URL to join.
 * @return string The URL string.
 */
function joinPaths(string ...$parts): string
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
