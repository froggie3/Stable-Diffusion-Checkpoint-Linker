<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Iigau\StableDiffusionCheckpointLinker\Config\ConfigurationLoader;

final class TestRunner
{
    private int $passed = 0;

    private int $failed = 0;

    public function run(string $name, callable $callback): void
    {
        try {
            $callback();
            ++$this->passed;
            echo "PASS $name\n";
        } catch (Throwable $e) {
            ++$this->failed;
            fwrite(STDERR, "FAIL $name: {$e->getMessage()}\n");
        }
    }

    public function report(): void
    {
        echo "{$this->passed} passed, {$this->failed} failed\n";
    }

    public function exitCode(): int
    {
        return $this->failed === 0 ? 0 : 1;
    }
}

$loader = new ConfigurationLoader();
$testRunner = new TestRunner();

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test(string $name, callable $callback): void
{
    global $testRunner;
    $testRunner->run($name, $callback);
}

function reject(ConfigurationLoader $loader, string $yaml, string $message): void
{
    try {
        $loader->load($yaml);
    } catch (InvalidArgumentException $e) {
        check(str_contains($e->getMessage(), $message), "Unexpected error: {$e->getMessage()}");
        return;
    }
    throw new RuntimeException('Invalid configuration was accepted.');
}

$sections = "source:\n  loras: []\ndestination:\n  loras: /tmp/models\n";
$valid = "lookupKeys: [loras]\n" . $sections;
$invalid = [
    'missing lookupKeys' => [$sections, 'lookupKeys'],
    'empty lookupKeys' => ["lookupKeys: []\n" . $sections, 'lookupKeys'],
    'null lookupKeys' => ["lookupKeys: null\n" . $sections, 'lookupKeys'],
    'scalar lookupKeys' => ["lookupKeys: loras\n" . $sections, 'lookupKeys'],
    'mapping lookupKeys' => ["lookupKeys: {name: loras}\n" . $sections, 'lookupKeys'],
    'empty category' => ["lookupKeys: ['']\n" . $sections, 'non-empty strings'],
    'whitespace category' => ["lookupKeys: ['  ']\n" . $sections, 'non-empty strings'],
    'numeric category' => ["lookupKeys: [123]\n" . $sections, 'non-empty strings'],
    'boolean category' => ["lookupKeys: [false]\n" . $sections, 'non-empty strings'],
    'duplicate category' => ["lookupKeys: [loras, loras]\n" . $sections, 'duplicate category'],
    'unknown source category' => ["lookupKeys: [missing]\n" . $sections, 'source.missing'],
    'missing source' => ["lookupKeys: [loras]\ndestination: {loras: /tmp/models}\n", 'source'],
    'scalar source' => ["lookupKeys: [loras]\nsource: invalid\ndestination: {loras: /tmp/models}\n", 'source'],
    'null source category' => ["lookupKeys: [loras]\nsource: {loras: null}\ndestination: {loras: /tmp/models}\n", 'source.loras'],
    'mapping source category' => ["lookupKeys: [loras]\nsource: {loras: {enabled: true}}\ndestination: {loras: /tmp/models}\n", 'source.loras'],
    'missing destination' => ["lookupKeys: [loras]\nsource: {loras: []}\n", 'destination'],
    'unknown destination category' => ["lookupKeys: [loras]\nsource: {loras: []}\ndestination: {}\n", 'destination.loras'],
    'empty destination path' => ["lookupKeys: [loras]\nsource: {loras: []}\ndestination: {loras: ''}\n", 'destination.loras'],
    'non-string destination path' => ["lookupKeys: [loras]\nsource: {loras: []}\ndestination: {loras: false}\n", 'destination.loras'],
    'empty document' => ['', 'mapping'],
    'scalar document' => ['some-file.yaml', 'mapping'],
    'sequence document' => ['[one, two]', 'mapping'],
    'malformed YAML' => ["lookupKeys: [loras\n", 'invalid YAML'],
    'duplicate YAML key' => ["lookupKeys: [loras]\nlookupKeys: [loras]\n" . $sections, 'invalid YAML'],
    'unsupported YAML tag' => ["lookupKeys: !php/object 'invalid'\n" . $sections, 'invalid YAML'],
];

foreach ($invalid as $name => [$yaml, $message]) {
    test($name, fn () => reject($loader, $yaml, $message));
}

test('valid selection with empty model groups', function () use ($loader, $valid): void {
    check($loader->load($valid)->lookupKeys === ['loras'], 'Selection changed.');
});

test('only selected categories need a destination', function () use ($loader, $valid): void {
    $yaml = str_replace("  loras: []\n", "  loras: []\n  unused: []\n", $valid);
    $config = $loader->load($yaml);
    check($config->lookupKeys === ['loras'], 'Selection expanded.');
});

test('multiple categories preserve selection order', function () use ($loader): void {
    $config = $loader->load("lookupKeys: [vae, loras]\nsource: {loras: [], vae: []}\ndestination: {loras: /tmp/loras, vae: /tmp/vae}\n");
    check($config->lookupKeys === ['vae', 'loras'], 'Selection order changed.');
});

$modelGroup = <<<'YAML'
source:
  loras:
    - meta:
        enabled: false
      baseDirectory: /tmp/source
      weightsList:
        - '001.safetensors'
        - '2026-10-30'
      ignoreList: []
destination:
  loras: /tmp/models
YAML;

test('ordinary indentation and comments', function () use ($loader, $modelGroup): void {
    $config = $loader->load("# Models\nlookupKeys:\n  - loras\n" . $modelGroup);
    $group = $config->category('loras')->groups[0];
    check($group->enabled === false, 'Boolean changed.');
    check($group->weightsList === ['001.safetensors', '2026-10-30'], 'Quoted strings changed.');
});

test('indentless sequences preserve model groups and weights', function () use ($loader, $modelGroup): void {
    $indented = "lookupKeys:\n  - loras\n" . $modelGroup;
    $indentless = <<<'YAML'
lookupKeys:
- loras
source:
  loras:
  - meta:
      enabled: false
    baseDirectory: /tmp/source
    weightsList:
    - '001.safetensors'
    - '2026-10-30'
    ignoreList: []
destination:
  loras: /tmp/models
YAML;
    $left = $loader->load($indented)->category('loras')->groups[0];
    $right = $loader->load($indentless)->category('loras')->groups[0];
    check(
        $left->enabled === $right->enabled && $left->baseDirectory === $right->baseDirectory
        && $left->weightsList === $right->weightsList && $left->ignoreList === $right->ignoreList,
        'Equivalent sequences parsed differently.'
    );
});

test('syntax error includes a line number', function () use ($loader): void {
    reject($loader, "lookupKeys: [loras\n", 'line');
});

test('distributed example loads', function () use ($loader): void {
    $config = $loader->load(file_get_contents(__DIR__ . '/../config/example.yaml'));
    check(count($config->lookupKeys) === 6, 'Example categories missing.');
    check(count($config->category('checkpoint')->groups) === 3, 'Example groups missing.');
});

test('README configuration example loads', function () use ($loader): void {
    $readme = file_get_contents(__DIR__ . '/../README.md');
    check(preg_match('/```yaml\R(.*?)```/s', $readme, $matches) === 1, 'README YAML missing.');
    check($loader->load($matches[1])->lookupKeys === ['checkpoint'], 'README selection changed.');
});

function runCommand(string $root, string $command, string $yaml, array $arguments = [], ?string $cwd = null, ?array $environment = null): array
{
    $processEnvironment = null;
    if ($environment !== null) {
        $processEnvironment = array_merge(getenv(), $environment);
    }
    $process = proc_open(["$root/bin/linker", $command, ...$arguments], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, $cwd ?? $root, $processEnvironment);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start CLI test.');
    }
    fwrite($pipes[0], $yaml);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
}

/** @return array{0:int,1:string,2:string} */
function runCommandWithOpenStdin(string $root, array $arguments): array
{
    $process = proc_open(["$root/bin/linker", ...$arguments], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, sys_get_temp_dir());
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start CLI help test.');
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + 3.0;
    do {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            return [$status['exitcode'], $stdout, $stderr];
        }
        usleep(10000);
    } while (microtime(true) < $deadline);

    proc_terminate($process);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    throw new RuntimeException('Help waited for stdin instead of exiting.');
}

function removeTemporaryDirectory(string $path): void
{
    foreach (new FilesystemIterator($path) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            removeTemporaryDirectory($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}

$root = sys_get_temp_dir() . '/linker-config-tests-' . bin2hex(random_bytes(8));
mkdir($root, 0700, true);
symlink(realpath(__DIR__ . '/../vendor'), $root . '/vendor');
mkdir($root . '/bin', 0700);
copy(__DIR__ . '/../bin/linker', "$root/bin/linker");
chmod("$root/bin/linker", 0700);
$editorScript = $root . '/fake editor.php';
file_put_contents($editorScript, "#!/usr/bin/env php\n<?php\nfile_put_contents(getenv('EDITOR_LOG'), json_encode(['args' => array_slice(\$argv, 1), 'cwd' => getcwd(), 'stdin' => stream_get_contents(STDIN)]));\nfwrite(STDOUT, \"fake editor stdout\\n\");\nfwrite(STDERR, \"fake editor stderr\\n\");\nusleep(50000);\nfile_put_contents(getenv('EDITOR_DONE'), 'done');\nexit((int) (getenv('EDITOR_STATUS') ?: '0'));\n");
chmod($editorScript, 0700);

try {
    test('global help is direct, complete, and does not initialize the DB', function () use ($root): void {
        [$status, $stdout, $stderr] = runCommandWithOpenStdin($root, []);
        check($status === 0 && $stderr === '', 'Global help failed.');
        check(str_contains($stdout, 'Commands:') && str_contains($stdout, 'sync') && str_contains($stdout, 'clear'), 'Global help omitted commands.');
        check(!file_exists($root . '/data'), 'Global help initialized the database.');
    });
    test('global help aliases do not read an open stdin pipe', function () use ($root): void {
        foreach ([['-h'], ['--help']] as $arguments) {
            [$status, $stdout, $stderr] = runCommandWithOpenStdin($root, $arguments);
            check($status === 0 && $stderr === '' && str_contains($stdout, 'Usage:'), 'Global help alias failed.');
        }
        check(!file_exists($root . '/data'), 'Global help created the database.');
    });
    test('global help with extra arguments is rejected without reading stdin', function () use ($root): void {
        foreach ([['--help', '--unknown'], ['-h', 'unexpected']] as $arguments) {
            [$status, $stdout, $stderr] = runCommandWithOpenStdin($root, $arguments);
            check($status === 2 && $stdout === '' && str_contains($stderr, 'argument error:'), 'Malformed global help arguments were accepted.');
        }
        check(!file_exists($root . '/data'), 'Malformed global help initialized the database.');
    });
    foreach (['sync', 'clear'] as $command) {
        test("$command detailed help is direct and does not read stdin or initialize the DB", function () use ($root, $command): void {
            [$status, $stdout, $stderr] = runCommandWithOpenStdin($root, [$command, '--help']);
            check($status === 0 && $stderr === '', "$command help failed.");
            check(str_contains($stdout, "Usage: ./bin/linker $command") && str_contains($stdout, 'Exit codes:'), "$command help is incomplete.");
            check(!file_exists($root . '/data'), "$command help initialized the database.");
        });
    }
    test('unknown command is lowercase stderr with status 2 and no DB access', function () use ($root): void {
        [$status, $stdout, $stderr] = runCommandWithOpenStdin($root, ['NO_SUCH_COMMAND']);
        check($status === 2 && $stdout === '' && str_contains($stderr, 'unknown command: NO_SUCH_COMMAND'), 'Unknown command response is incorrect.');
        check(!file_exists($root . '/data'), 'Unknown command initialized the database.');
    });
    test('edit help does not read stdin or initialize the DB', function () use ($root): void {
        [$status, $stdout, $stderr] = runCommandWithOpenStdin($root, ['edit', '--help']);
        check($status === 0 && $stderr === '', 'edit help failed.');
        check(str_contains($stdout, 'Usage: ./bin/linker edit CONFIG [EDITOR [ARGS...]]'), 'edit help is incomplete.');
        check(!file_exists($root . '/data'), 'edit help initialized the database.');
    });
    test('edit without CONFIG is an argument error', function () use ($root): void {
        [$status, $stdout, $stderr] = runCommand($root, 'edit', '', []);
        check($status === 2 && $stdout === '', 'edit without CONFIG was accepted.');
        check(str_contains($stderr, 'argument error:'), 'edit without CONFIG omitted the argument diagnostic.');
    });
    test('edit rejects option-like CONFIGs without starting an editor', function () use ($root, $editorScript): void {
        foreach ([['--dry-run'], ['--category', 'models'], ['--unknown'], ['--help', 'extra']] as $arguments) {
            $log = $root . '/should-not-run.json';
            [$status, $stdout, $stderr] = runCommand(
                $root,
                'edit',
                '',
                $arguments,
                null,
                ['EDITOR_LOG' => $log, 'EDITOR_DONE' => $root . '/should-not-finish', 'EDITOR_STATUS' => '0', 'VISUAL' => '', 'EDITOR' => '']
            );
            check($status === 2 && $stdout === '', 'Option-like CONFIG was accepted: ' . implode(' ', $arguments));
            check(str_contains($stderr, 'argument error:'), 'Option-like CONFIG did not report an argument error.');
            check(!file_exists($log), 'Editor started for an option-like CONFIG.');
            check(!file_exists($root . '/should-not-finish'), 'Editor completed for an option-like CONFIG.');
        }
        $dashFile = './-config.yaml';
        file_put_contents($root . '/' . $dashFile, 'bad yaml');
        $log = $root . '/dash-file-editor.json';
        [$status] = runCommand(
            $root,
            'edit',
            '',
            [$dashFile, $editorScript],
            null,
            ['EDITOR_LOG' => $log, 'EDITOR_DONE' => $root . '/dash-file-done', 'EDITOR_STATUS' => '0', 'VISUAL' => '', 'EDITOR' => '']
        );
        check($status === 0 && file_exists($log), 'Explicit ./-config.yaml path was rejected.');
    });
    test('edit explicit editor receives literal argv and final CONFIG with inherited streams and cwd', function () use ($root, $editorScript): void {
        file_put_contents($root . '/relative config.yaml', 'not valid YAML');
        $log = $root . '/editor.json';
        $done = $root . '/editor.done';
        $literal = "x'; touch should-not-exist; echo 'y";
        [$status, $stdout, $stderr] = runCommand(
            $root,
            'edit',
            'stdin reaches editor',
            ['relative config.yaml', $editorScript, 'argument with spaces', $literal, '--help'],
            null,
            ['EDITOR_LOG' => $log, 'EDITOR_DONE' => $done, 'EDITOR_STATUS' => '0', 'VISUAL' => 'broken \' command', 'EDITOR' => 'missing-editor']
        );
        check($status === 0, "Explicit editor failed: $stderr");
        check($stdout === "fake editor stdout\n", 'Editor stdout was not inherited.');
        check(str_contains($stderr, "fake editor stderr\n"), 'Editor stderr was not inherited.');
        $record = json_decode((string) file_get_contents($log), true, flags: JSON_THROW_ON_ERROR);
        check($record['args'] === ['argument with spaces', $literal, '--help', 'relative config.yaml'], 'Explicit editor argv boundaries or final CONFIG changed.');
        check($record['cwd'] === $root, 'Editor cwd was not inherited.');
        check($record['stdin'] === 'stdin reaches editor', 'Editor stdin was not inherited.');
        check(file_exists($done), 'CLI returned before the editor completed.');
        check(!file_exists($root . '/should-not-exist'), 'Explicit editor arguments were interpreted by a shell.');
        check(!file_exists($root . '/data'), 'edit initialized the database.');
    });
    test('edit uses VISUAL as a shell command and passes CONFIG as a separate final argument', function () use ($root, $editorScript): void {
        $log = $root . '/visual-editor.json';
        $config = "config 'quoted' \$(touch should-not-exist).yaml";
        file_put_contents($root . '/' . $config, 'bad yaml');
        $visual = escapeshellarg($editorScript) . " --visual 'value with spaces'";
        $done = $root . '/visual-editor.done';
        [$status, $stdout, $stderr] = runCommand(
            $root,
            'edit',
            '',
            [$config],
            null,
            ['EDITOR_LOG' => $log, 'EDITOR_DONE' => $done, 'EDITOR_STATUS' => '0', 'VISUAL' => $visual, 'EDITOR' => 'missing-editor']
        );
        check($status === 0 && $stderr === "fake editor stderr\n", "VISUAL editor failed: $stderr");
        check($stdout === "fake editor stdout\n", 'VISUAL editor stdout was not inherited.');
        $record = json_decode((string) file_get_contents($log), true, flags: JSON_THROW_ON_ERROR);
        check($record['args'] === ['--visual', 'value with spaces', $config], 'VISUAL command arguments or CONFIG boundary changed.');
        check(file_exists($done), 'CLI returned before the environment editor completed.');
        check(!file_exists($root . '/should-not-exist'), 'CONFIG shell metacharacters were interpreted by the shell.');
        check(!file_exists($root . '/data'), 'VISUAL edit initialized the database.');
    });
    test('edit falls back from blank VISUAL to EDITOR and reports editor failure as status 1', function () use ($root, $editorScript): void {
        $log = $root . '/fallback-editor.json';
        $done = $root . '/fallback-editor.done';
        $editor = escapeshellarg($editorScript) . ' --fallback';
        [$status, $stdout, $stderr] = runCommand(
            $root,
            'edit',
            '',
            ['config.yaml'],
            null,
            ['EDITOR_LOG' => $log, 'EDITOR_DONE' => $done, 'EDITOR_STATUS' => '7', 'VISUAL' => '  ', 'EDITOR' => $editor]
        );
        check($status === 1, 'Nonzero editor status was not mapped to failure.');
        check($stdout === "fake editor stdout\n", 'Failed editor stdout was not inherited.');
        check(str_contains($stderr, "fake editor stderr\n") && str_contains($stderr, 'editor exited with status 7'), 'Editor failure details were not reported.');
        $record = json_decode((string) file_get_contents($log), true, flags: JSON_THROW_ON_ERROR);
        check($record['args'] === ['--fallback', 'config.yaml'], 'EDITOR fallback did not receive CONFIG last.');
        check(file_exists($done), 'CLI returned before the failing editor completed.');
    });
    test('edit without explicit or environment editor is an argument error', function () use ($root): void {
        [$status, $stdout, $stderr] = runCommand(
            $root,
            'edit',
            '',
            ['config.yaml'],
            null,
            ['VISUAL' => '', 'EDITOR' => '']
        );
        check($status === 2 && $stdout === '', 'Missing editor was not an argument error.');
        check(str_contains($stderr, 'VISUAL and EDITOR are unset'), 'Missing editor diagnostic is incomplete.');
        check(!file_exists($root . '/data'), 'Missing editor initialized the database.');
    });
    test('missing explicit editor executable fails with status 1', function () use ($root): void {
        [$status, $stdout, $stderr] = runCommand(
            $root,
            'edit',
            '',
            ['config.yaml', '/no/such/editor'],
            null,
            ['VISUAL' => 'broken \' command', 'EDITOR' => 'also-invalid']
        );
        check($status === 1 && $stdout === '', 'Missing explicit editor did not fail with status 1.');
        check(str_contains($stderr, 'editor'), 'Missing explicit editor did not report a failure.');
    });
    test('repository entrypoint is executable', function (): void {
        check(is_executable(__DIR__ . '/../bin/linker'), 'bin/linker is missing its executable permission.');
    });
    test('executable runs from a different working directory', function () use ($root, $valid): void {
        [$status, $stdout, $stderr] = runCommand($root, 'sync', $valid, ['--dry-run'], sys_get_temp_dir());
        check($status === 0 && $stderr === '', "Direct executable from another cwd failed: $stderr");
        check(str_contains($stdout, 'dry run: no filesystem or database changes made.'), 'Dry-run output is missing.');
        check(!file_exists($root . '/data'), 'Dry-run from another cwd initialized the database.');
    });

    test('configuration file argument ignores stdin and allows options on either side', function () use ($root, $valid): void {
        $config = $root . '/config.yaml';
        file_put_contents($config, $valid);
        [$status, $stdout, $stderr] = runCommand($root, 'sync', 'not: valid: yaml', ['--dry-run', $config, '--verbose']);
        check($status === 0 && $stderr === '', "Config file execution failed: $stderr");
        check(str_contains($stdout, 'dry run: no filesystem or database changes made.'), 'Config file did not supply input.');
        check(!file_exists($root . '/data'), 'Config file dry-run initialized the database.');
    });

    test('clear configuration argument does not wait for stdin', function () use ($root, $valid): void {
        $config = $root . '/clear-config.yaml';
        file_put_contents($config, $valid);
        [$status, $stdout, $stderr] = runCommandWithOpenStdin($root, ['clear', '--dry-run', $config]);
        check($status === 0 && $stderr === '', "clear with CONFIG failed: $stderr");
        check(str_contains($stdout, 'dry run: no filesystem or database changes made.'), 'clear did not use CONFIG.');
        check(!file_exists($root . '/data'), 'clear config dry-run initialized the database.');
    });

    test('-- permits a configuration file whose name begins with a dash', function () use ($root, $valid): void {
        file_put_contents($root . '/-config.yaml', $valid);
        [$status, $stdout, $stderr] = runCommand($root, 'sync', 'ignored stdin', ['--dry-run', '--', '-config.yaml']);
        check($status === 0 && $stderr === '', "Dash-prefixed config failed: $stderr");
        check(str_contains($stdout, 'dry run: no filesystem or database changes made.'), 'Dash-prefixed config was not read.');
    });

    test('extra configuration positional argument is rejected', function () use ($root, $valid): void {
        $config = $root . '/config.yaml';
        file_put_contents($config, $valid);
        [$status, $stdout, $stderr] = runCommand($root, 'sync', '', [$config, 'extra.yaml']);
        check($status === 2 && $stdout === '', 'Extra positional argument was accepted.');
        check(str_contains($stderr, 'unexpected positional argument: extra.yaml'), 'Missing positional argument diagnostic.');
    });

    test('empty configuration positional argument is rejected', function () use ($root): void {
        [$status, $stdout, $stderr] = runCommand($root, 'sync', '', ['']);
        check($status === 2 && $stdout === '', 'Empty config path was not rejected as an argument error.');
        check(str_contains($stderr, 'configuration file path must not be empty'), 'Missing empty config diagnostic.');
    });

    test('configuration file read failure is reported without PHP warning output', function () use ($root): void {
        [$status, $stdout, $stderr] = runCommand($root, 'sync', '', [$root . '/missing.yaml']);
        check($status === 1 && $stdout === '', 'Missing config file did not fail.');
        check(str_contains($stderr, 'configuration error: failed to read configuration from '), 'Missing config did not use configuration diagnostic.');
        check(!str_contains($stderr, 'Warning') && !str_contains($stderr, 'Fatal error'), 'PHP warning leaked to stderr.');
        check(!file_exists($root . '/data'), 'Missing config initialized the database.');
    });

    foreach (['sync', 'clear'] as $command) {
        test("$command rejects invalid configuration before DB initialization", function () use ($root, $command, $invalid): void {
            foreach ($invalid as $name => [$yaml, $message]) {
                [$status, $stdout, $stderr] = runCommand($root, $command, $yaml);
                check($status === 1, "$name: expected exit 1, received $status");
                check($stdout === '', "$name: unexpected success output");
                check(str_contains($stderr, 'configuration error: '), "$name: missing configuration diagnostic");
                check(!str_contains($stderr, 'Fatal error'), "$name: unexpected uncaught exception");
                check(!file_exists($root . '/data'), "$name: created database directory");
            }
        });
    }

    mkdir($root . '/data', 0700);
    foreach (['sync', 'clear'] as $command) {
        test("$command accepts valid configuration with an isolated DB", function () use ($root, $command, $valid): void {
            [$status, $stdout, $stderr] = runCommand($root, $command, $valid);
            check($status === 0, "Exit $status: $stderr");
            check($stdout === '' && $stderr === '', 'Successful execution should be silent by default.');
            $db = new PDO('sqlite:' . $root . '/data/links.sqlite');
            check((int) $db->query('SELECT COUNT(*) FROM links')->fetchColumn() === 0, 'Unexpected link records.');
        });
    }
} finally {
    removeTemporaryDirectory($root);
}

function plannedLinkForTest(string $source, string $target, string $root, string $action = 'create'): \Iigau\StableDiffusionCheckpointLinker\Sync\PlannedOperation
{
    $parent = realpath(dirname($target));
    $stat = $parent === false ? false : stat(dirname($target));
    return new \Iigau\StableDiffusionCheckpointLinker\Sync\PlannedOperation(
        \Iigau\StableDiffusionCheckpointLinker\Sync\OperationType::LINK,
        $source,
        $target,
        \Iigau\StableDiffusionCheckpointLinker\Sync\OperationAction::from($action),
        new \Iigau\StableDiffusionCheckpointLinker\Sync\OperationSnapshot(
            \Iigau\StableDiffusionCheckpointLinker\Link\FilesystemKind::ABSENT,
            null,
            null,
            new \Iigau\StableDiffusionCheckpointLinker\Sync\DirectorySnapshot(
                $parent === false ? null : $parent,
                $stat === false ? null : $stat['dev'],
                $stat === false ? null : $stat['ino'],
                $parent !== false && is_dir(dirname($target))
            ),
            realpath($root) ?: null,
            $root
        )
    );
}

function testRoot(string $label): string
{
    $root = sys_get_temp_dir() . "/linker-$label-" . bin2hex(random_bytes(8));
    mkdir($root . '/source', 0700, true);
    mkdir($root . '/target', 0700, true);
    return $root;
}

$testSource = static function (string $root): string {
    $path = $root . '/source/model.bin';
    file_put_contents($path, 'model');
    return $path;
};

$newTestRepository = static function (bool $failUpsert = false): \Iigau\StableDiffusionCheckpointLinker\Record\LinkRecordRepository {
    return new class ($failUpsert) implements \Iigau\StableDiffusionCheckpointLinker\Record\LinkRecordRepository {
        private array $records = [];
        public function __construct(private bool $failUpsert)
        {
        }
        public function hasRecord(string $target): bool
        {
            return isset($this->records[$target]);
        }
        public function getSource(string $target): ?string
        {
            return $this->records[$target] ?? null;
        }
        public function upsert(string $source, string $target): void
        {
            if ($this->failUpsert) {
                throw new RuntimeException('injected DB upsert failure');
            }
            $this->records[$target] = $source;
        }
        public function deleteRecord(string $target): bool
        {
            unset($this->records[$target]);
            return true;
        }
        public function activateForWrites(): void
        {
        }
    };
};

test('executor stops after a failure and reports unexecuted operations', function () use ($testSource, $newTestRepository): void {
    $root = testRoot('fail-fast');
    try {
        $source = $testSource($root);
        $linker = new class () implements \Iigau\StableDiffusionCheckpointLinker\Link\LinkerInterface {
            private \Iigau\StableDiffusionCheckpointLinker\Link\Linker $actual;
            public function __construct()
            {
                $this->actual = new \Iigau\StableDiffusionCheckpointLinker\Link\Linker();
            }
            public function inspect(string $path): \Iigau\StableDiffusionCheckpointLinker\Link\FilesystemEntry
            {
                return $this->actual->inspect($path);
            }
            public function createLink(string $source, string $target): void
            {
                if (str_ends_with($target, 'two.bin')) {
                    throw new RuntimeException('injected link failure');
                }
                $this->actual->createLink($source, $target);
            }
            public function removeLink(string $target): void
            {
                $this->actual->removeLink($target);
            }
        };
        $plan = new \Iigau\StableDiffusionCheckpointLinker\Sync\OperationPlan();
        $plan->add(plannedLinkForTest($source, $root . '/target/one.bin', $root . '/target'));
        $plan->add(plannedLinkForTest($source, $root . '/target/two.bin', $root . '/target'));
        $plan->add(plannedLinkForTest($source, $root . '/target/three.bin', $root . '/target'));
        $executor = new \Iigau\StableDiffusionCheckpointLinker\Sync\OperationExecutor($linker, $newTestRepository(), new \Iigau\StableDiffusionCheckpointLinker\Link\PathIdentity());
        $result = (new \Iigau\StableDiffusionCheckpointLinker\Sync\Synchronizer($plan, $executor))->run();
        check($result->completedCount === 1, 'First successful operation was not retained.');
        check(count($result->completedOperations) === 1 && str_contains($result->completedOperations[0], 'one.bin'), 'Completed target was not reported.');
        check($result->failedOperation !== null && str_contains($result->failedOperation, 'two.bin'), 'Failed target was not reported.');
        check(count($result->unexecutedOperations) === 1 && str_contains($result->unexecutedOperations[0], 'three.bin'), 'Unexecuted target was not reported.');
        check(is_link($root . '/target/one.bin') && readlink($root . '/target/one.bin') === $source, 'First operation effect was not preserved.');
        check(!file_exists($root . '/target/two.bin') && !file_exists($root . '/target/three.bin'), 'Fail-fast executed an operation after failure.');
    } finally {
        removeTemporaryDirectory($root);
    }
});

test('database failure reports a link already created as a partial effect', function () use ($testSource, $newTestRepository): void {
    $root = testRoot('db-failure');
    try {
        $source = $testSource($root);
        $plan = new \Iigau\StableDiffusionCheckpointLinker\Sync\OperationPlan();
        $plan->add(plannedLinkForTest($source, $root . '/target/model.bin', $root . '/target'));
        $executor = new \Iigau\StableDiffusionCheckpointLinker\Sync\OperationExecutor(new \Iigau\StableDiffusionCheckpointLinker\Link\Linker(), $newTestRepository(true), new \Iigau\StableDiffusionCheckpointLinker\Link\PathIdentity());
        $result = (new \Iigau\StableDiffusionCheckpointLinker\Sync\Synchronizer($plan, $executor))->run();
        check($result->failedOperation !== null, 'Injected database failure was not reported.');
        check(is_link($root . '/target/model.bin'), 'Created link was incorrectly rolled back or missing.');
        check(in_array('created link', $result->partialEffects, true), 'Partial filesystem effect was not reported.');
    } finally {
        removeTemporaryDirectory($root);
    }
});

test('executor detects target state changes after planning', function () use ($testSource, $newTestRepository): void {
    $root = testRoot('changed-state');
    try {
        $source = $testSource($root);
        $target = $root . '/target/model.bin';
        $plan = new \Iigau\StableDiffusionCheckpointLinker\Sync\OperationPlan();
        $plan->add(plannedLinkForTest($source, $target, $root . '/target'));
        file_put_contents($target, 'external');
        $executor = new \Iigau\StableDiffusionCheckpointLinker\Sync\OperationExecutor(new \Iigau\StableDiffusionCheckpointLinker\Link\Linker(), $newTestRepository(), new \Iigau\StableDiffusionCheckpointLinker\Link\PathIdentity());
        $result = (new \Iigau\StableDiffusionCheckpointLinker\Sync\Synchronizer($plan, $executor))->run();
        check($result->failedOperation !== null, 'Target race was not detected.');
        check(file_get_contents($target) === 'external', 'Changed target was modified.');
    } finally {
        removeTemporaryDirectory($root);
    }
});

test('executor rechecks link source immediately before mutation', function () use ($testSource, $newTestRepository): void {
    $root = testRoot('missing-source-race');
    try {
        $source = $testSource($root);
        $target = $root . '/target/model.bin';
        $plan = new \Iigau\StableDiffusionCheckpointLinker\Sync\OperationPlan();
        $plan->add(plannedLinkForTest($source, $target, $root . '/target'));
        unlink($source);
        $executor = new \Iigau\StableDiffusionCheckpointLinker\Sync\OperationExecutor(new \Iigau\StableDiffusionCheckpointLinker\Link\Linker(), $newTestRepository(), new \Iigau\StableDiffusionCheckpointLinker\Link\PathIdentity());
        $result = (new \Iigau\StableDiffusionCheckpointLinker\Sync\Synchronizer($plan, $executor))->run();
        check($result->failedOperation !== null, 'Missing source race was not detected.');
        check(!is_link($target), 'A dangling link was created.');
    } finally {
        removeTemporaryDirectory($root);
    }
});

test('failure printer reports completed work, partial effects, failed path, and pending work to error stream', function (): void {
    $stream = fopen('php://memory', 'r+');
    $handler = new Monolog\Handler\StreamHandler($stream, Monolog\Level::Error);
    $handler->setFormatter(new Monolog\Formatter\LineFormatter("%message%\n"));
    $logger = new Monolog\Logger('test');
    $logger->pushHandler($handler);
    $logger->pushProcessor(new Monolog\Processor\PsrLogMessageProcessor());
    $path = '/tmp/ModelCase/Checkpoint.safetensors';
    $result = new \Iigau\StableDiffusionCheckpointLinker\Sync\SynchronizerResult(
        new \Iigau\StableDiffusionCheckpointLinker\Sync\OperationCounts(created: 1),
        3,
        1,
        ['link /tmp/source.safetensors -> ' . $path . ' (create)'],
        'link /tmp/source2.safetensors -> ' . $path . ' (create)',
        'database write failed',
        ['created link'],
        ['unlink /tmp/ModelCase/other.safetensors (unlink)']
    );
    (new \Iigau\StableDiffusionCheckpointLinker\Console\SyncResultPrinter($logger))->print($result, false);
    rewind($stream);
    $diagnostic = stream_get_contents($stream);
    fclose($stream);
    check(str_contains($diagnostic, 'completed: link /tmp/source.safetensors -> ' . $path), 'Completed operation missing.');
    check(str_contains($diagnostic, 'partial effect before failure: created link'), 'Partial effect missing.');
    check(str_contains($diagnostic, 'failed: link /tmp/source2.safetensors -> ' . $path . ' (create): database write failed'), 'Failure status missing.');
    check(str_contains($diagnostic, 'not executed: unlink /tmp/ModelCase/other.safetensors'), 'Pending operation missing.');
    check(str_contains($diagnostic, $path), 'Path case was changed in diagnostics.');
});

test('destination parent file is rejected before database initialization', function (): void {
    $root = sys_get_temp_dir() . '/linker-parent-file-' . bin2hex(random_bytes(8));
    mkdir($root, 0700, true);
    mkdir($root . '/source', 0700, true);
    symlink(realpath(__DIR__ . '/../vendor'), $root . '/vendor');
    mkdir($root . '/bin', 0700);
    copy(__DIR__ . '/../bin/linker', "$root/bin/linker");
    chmod("$root/bin/linker", 0700);
    file_put_contents($root . '/source/model.bin', 'model');
    file_put_contents($root . '/destination-file', 'keep');
    $yaml = "lookupKeys: [models]\nsource:\n  models:\n    - meta: {enabled: true}\n      baseDirectory: $root/source\n      weightsList: [model.bin]\ndestination: {models: $root/destination-file}\n";
    try {
        [$status, , $stderr] = runCommand($root, 'sync', $yaml);
        check($status === 1, 'Expected preflight exit 1 for a regular-file destination parent.');
        check(str_contains($stderr, 'preflight error:'), 'Missing preflight diagnostic.');
        check(!file_exists($root . '/data/links.sqlite'), 'Preflight created a database.');
        check(file_get_contents($root . '/destination-file') === 'keep', 'Destination file was modified.');
    } finally {
        removeTemporaryDirectory($root);
    }
});

require __DIR__ . '/safety.php';

$testRunner->report();
exit($testRunner->exitCode());
