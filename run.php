#!/usr/bin/php
<?php

declare(strict_types=1);
require_once("Linker.php");
require_once("NewConfig.php");

$defined_cmds = array('newconfig' => 'newconfig');
$has_newconfig = in_array($defined_cmds['newconfig'], $argv) ?: false;

if ($has_newconfig) {
    $np = new NewConfig($defined_cmds['newconfig'], $argv);
    $np->run();
} else {
    $ln = new Linker;
    $ln->run();
}

/**
 * Join string into a single URL string.
 *
 * @param string $parts,... The parts of the URL to join.
 * @return string The URL string.
 */
function join_paths(string ...$parts): string
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
