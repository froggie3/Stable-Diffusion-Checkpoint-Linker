<?php

declare(strict_types=1);

// list operates on a disposable installation root, independently of the cwd.
test('list recursively prints sorted YAML paths without following directory symlinks or creating a DB', function (): void {
    safetyScenario(function (string $root): void {
        mkdir($root . '/config/nested/deeper', 0700, true);
        mkdir($root . '/outside', 0700);
        file_put_contents($root . '/config/z.yaml', 'not necessarily valid YAML');
        file_put_contents($root . '/config/a.yml', '');
        file_put_contents($root . '/config/nested/B.YAML', '');
        file_put_contents($root . '/config/nested/deeper/c.YmL', '');
        file_put_contents($root . '/config/nested/ignore.txt', '');
        file_put_contents($root . '/outside/hidden.yaml', '');
        symlink($root . '/outside', $root . '/config/nested/linked');
        symlink($root . '/config', $root . '/config/nested/cycle');

        [$status, $stdout, $stderr] = runCommand($root, 'list', '', [], sys_get_temp_dir());
        $paths = [
            $root . '/config/a.yml',
            $root . '/config/nested/B.YAML',
            $root . '/config/nested/deeper/c.YmL',
            $root . '/config/z.yaml',
        ];
        sort($paths, SORT_STRING);
        check($status === 0 && $stderr === '', "list failed: $stderr");
        check($stdout === implode("\n", $paths) . "\n", "Unexpected list output: $stdout");
        check(!file_exists($root . '/data'), 'list initialized the database.');
    });
});

test('list of an empty configuration directory is silent and does not initialize a DB', function (): void {
    safetyScenario(function (string $root): void {
        mkdir($root . '/config', 0700);
        [$status, $stdout, $stderr] = runCommand($root, 'list', 'ignored stdin');
        check($status === 0 && $stdout === '' && $stderr === '', 'Empty list did not succeed silently.');
        check(!file_exists($root . '/data'), 'Empty list initialized the database.');
    });
});

test('list reports a missing or non-directory configuration path without initializing a DB', function (): void {
    foreach ([false, true] as $regularFile) {
        safetyScenario(function (string $root) use ($regularFile): void {
            if ($regularFile) {
                file_put_contents($root . '/config', 'not a directory');
            }
            [$status, $stdout, $stderr] = runCommand($root, 'list', '');
            check($status === 1 && $stdout === '' && $stderr !== '', 'Invalid configuration directory did not report an error.');
            check(!str_contains($stderr, 'Warning') && !str_contains($stderr, 'Fatal error'), 'PHP diagnostic leaked to stderr.');
            check(!file_exists($root . '/data'), 'Failed list initialized the database.');
        });
    }
});

test('list rejects unexpected arguments without initializing a DB', function (): void {
    safetyScenario(function (string $root): void {
        mkdir($root . '/config', 0700);
        foreach ([['extra'], ['--unknown']] as $arguments) {
            [$status, $stdout, $stderr] = runCommand($root, 'list', '', $arguments);
            check($status === 2 && $stdout === '' && $stderr !== '', 'Invalid list arguments were accepted.');
        }
        check(!file_exists($root . '/data'), 'Invalid list invocation initialized the database.');
    });
});
