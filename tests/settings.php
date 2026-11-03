<?php

declare(strict_types=1);

use Iigau\StableDiffusionCheckpointLinker\Config\ApplicationSettingsLoader;
use Symfony\Component\Yaml\Yaml;

/** Run settings cases in an isolated project with a disposable CLI. */
function settingsScenario(callable $callback): void
{
    $root = sys_get_temp_dir() . '/linker-settings-' . bin2hex(random_bytes(8));
    mkdir($root, 0700, true);
    mkdir($root . '/bin', 0700);
    symlink(realpath(__DIR__ . '/../vendor'), $root . '/vendor');
    copy(__DIR__ . '/../bin/linker', $root . '/bin/linker');
    chmod($root . '/bin/linker', 0700);
    try {
        $callback($root);
    } finally {
        settingsRemoveTree($root);
    }
}

function settingsRemoveTree(string $path): void
{
    if (!is_dir($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (new FilesystemIterator($path) as $entry) {
        settingsRemoveTree($entry->getPathname());
    }
    @rmdir($path);
}

/** @return array{int,string,string} */
function settingsRunCli(string $root, string $cwd, string $command, string $yaml): array
{
    $process = proc_open([$root . '/bin/linker', $command], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start settings CLI process.');
    }
    fwrite($pipes[0], $yaml);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    clearstatcache(true);
    return [$status, $stdout, $stderr];
}

test('settings defaults and partial overrides are rooted at the project', function (): void {
    settingsScenario(function (string $root): void {
        $loader = new ApplicationSettingsLoader();
        $settings = $loader->load($root . '/');
        check($settings->configDirectory === "$root/config", 'Default config directory is incorrect.');
        check($settings->databasePath === "$root/data/links.sqlite", 'Default database path is incorrect.');
        file_put_contents("$root/setting.ini", "configuration_dir = custom/config\n");
        $settings = $loader->load($root);
        check($settings->configDirectory === "$root/custom/config" && $settings->databasePath === "$root/data/links.sqlite", 'Partial settings did not retain database default.');
    });
});

test('settings resolve relative paths against project root and preserve absolute paths', function (): void {
    settingsScenario(function (string $root): void {
        $loader = new ApplicationSettingsLoader();
        file_put_contents("$root/setting.ini", "configuration_dir = relative/config\ndatabase_path = relative/links.sqlite\n");
        $settings = $loader->load($root);
        check($settings->configDirectory === "$root/relative/config" && $settings->databasePath === "$root/relative/links.sqlite", 'Relative paths were not rooted at project.');
        file_put_contents("$root/setting.ini", "configuration_dir = /absolute/config\ndatabase_path = /absolute/links.sqlite\n");
        $settings = $loader->load($root);
        check($settings->configDirectory === '/absolute/config' && $settings->databasePath === '/absolute/links.sqlite', 'Absolute paths were changed.');
    });
});

test('settings reject malformed, unknown, empty and structured values', function (): void {
    settingsScenario(function (string $root): void {
        $loader = new ApplicationSettingsLoader();
        foreach ([
            "[bad\n" => 'invalid INI',
            "unknown = value\n" => 'unknown setting',
            "database_path = \"  \"\n" => 'database_path',
            "configuration_dir = \"\"\n" => 'configuration_dir',
            "database_path[] = foo\n" => 'database_path',
            "[database_path]\nfoo = bar\n" => 'database_path',
            "[other]\nfoo = bar\n" => 'unknown setting',
        ] as $ini => $expected) {
            file_put_contents("$root/setting.ini", $ini);
            try {
                $loader->load($root);
            } catch (InvalidArgumentException $e) {
                check(str_contains($e->getMessage(), $expected), "Unexpected settings error: {$e->getMessage()}");
                continue;
            }
            throw new RuntimeException("Invalid settings accepted: $ini");
        }
    });
});

test('CLI list uses custom relative and absolute configuration directories independent of cwd', function (): void {
    foreach (['relative', 'absolute'] as $kind) {
        settingsScenario(function (string $root) use ($kind): void {
            mkdir("$root/custom", 0700);
            mkdir("$root/elsewhere", 0700);
            file_put_contents("$root/custom/example.yaml", 'example');
            $path = $kind === 'relative' ? 'custom' : "$root/custom";
            file_put_contents("$root/setting.ini", "configuration_dir = \"$path\"\n");
            [$status, $stdout, $stderr] = settingsRunCli($root, "$root/elsewhere", 'list', '');
            check($status === 0 && $stderr === '' && $stdout === "$root/custom/example.yaml\n", "$kind list used the wrong configuration directory: $stderr $stdout");
            check(!file_exists("$root/data"), 'list initialized the database.');
        });
    }
});

test('CLI rejects malformed settings without initializing database', function (): void {
    settingsScenario(function (string $root): void {
        file_put_contents("$root/setting.ini", "[bad\n");
        foreach (['list', 'sync', 'clear'] as $command) {
            [$status, $stdout, $stderr] = settingsRunCli($root, $root, $command, '');
            check($status === 1 && $stdout === '' && str_contains($stderr, 'invalid INI'), "$command did not report malformed settings: $stderr");
            check(!file_exists("$root/data"), "$command initialized the database with malformed settings.");
        }
    });
});

test('CLI sync and clear use custom relative and absolute databases independent of cwd', function (): void {
    foreach (['relative', 'absolute'] as $kind) {
        settingsScenario(function (string $root) use ($kind): void {
            mkdir("$root/source", 0700);
            mkdir("$root/destination", 0700);
            mkdir("$root/elsewhere", 0700);
            file_put_contents("$root/source/a.bin", 'payload');
            $dbPath = "$root/custom/links.sqlite";
            $configured = $kind === 'relative' ? 'custom/links.sqlite' : $dbPath;
            file_put_contents("$root/setting.ini", "database_path = \"$configured\"\n");
            $yaml = Yaml::dump([
                'lookupKeys' => ['models'],
                'source' => ['models' => [[
                    'meta' => ['enabled' => true],
                    'baseDirectory' => "$root/source",
                    'weightsList' => ['a.bin'],
                    'ignoreList' => [],
                ]]],
                'destination' => ['models' => "$root/destination"],
            ], 12, 2);
            [$status, , $error] = settingsRunCli($root, "$root/elsewhere", 'sync', $yaml);
            check($status === 0, "$kind sync failed ($status): $error");
            check(is_file($dbPath) && !file_exists("$root/data/links.sqlite") && !file_exists("$root/elsewhere/custom/links.sqlite"), "$kind sync used the wrong database path.");
            check(is_link("$root/destination/a.bin"), "$kind sync did not create the link.");
            $db = new PDO('sqlite:' . $dbPath);
            $records = $db->query('SELECT target, source FROM links')->fetchAll(PDO::FETCH_KEY_PAIR);
            check($records === ["$root/destination/a.bin" => "$root/source/a.bin"], "$kind sync did not record the link in the custom database.");
            $db = null;
            [$status, , $error] = settingsRunCli($root, "$root/elsewhere", 'clear', $yaml);
            check($status === 0, "$kind clear failed ($status): $error");
            $db = new PDO('sqlite:' . $dbPath);
            check((int) $db->query('SELECT COUNT(*) FROM links')->fetchColumn() === 0 && !is_link("$root/destination/a.bin"), "$kind clear did not remove the custom database record and link.");
            check(!file_exists("$root/data/links.sqlite") && !file_exists("$root/elsewhere/custom/links.sqlite"), "$kind clear used the wrong database path.");
        });
    }
});
