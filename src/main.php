#!/usr/bin/php
<?php

declare(strict_types=1);

spl_autoload_register(function ($class_name) {
    $dir = dirname(__DIR__) . '/classes/';
    $file = $dir . str_replace('\\', '/', $class_name) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

$defined_cmds = array('newconfig' => 'newconfig');
$has_newconfig = in_array($defined_cmds['newconfig'], $argv) ?: false;

if ($has_newconfig) {
    (new NewConfig($defined_cmds['newconfig'], $argv))->run();
} else {
    (new Linker)->run();
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
