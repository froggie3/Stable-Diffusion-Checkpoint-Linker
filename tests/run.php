<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Iigau\StableDiffusionCheckpointLinker\Config\ConfigurationLoader;

$loader = new ConfigurationLoader();
$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test(string $name, callable $callback): void
{
    global $passed, $failed;
    try {
        $callback();
        ++$passed;
        echo "PASS $name\n";
    } catch (Throwable $e) {
        ++$failed;
        fwrite(STDERR, "FAIL $name: {$e->getMessage()}\n");
    }
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
    'duplicate category' => ["lookupKeys: [loras, loras]\n" . $sections, 'Duplicate category'],
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
    'malformed YAML' => ["lookupKeys: [loras\n", 'Invalid YAML'],
    'duplicate YAML key' => ["lookupKeys: [loras]\nlookupKeys: [loras]\n" . $sections, 'Invalid YAML'],
    'unsupported YAML tag' => ["lookupKeys: !php/object 'invalid'\n" . $sections, 'Invalid YAML'],
];

foreach ($invalid as $name => [$yaml, $message]) {
    test($name, fn () => reject($loader, $yaml, $message));
}

test('valid selection with empty model groups', function () use ($loader, $valid): void {
    check($loader->load($valid)['lookupKeys'] === ['loras'], 'Selection changed.');
});

test('only selected categories need a destination', function () use ($loader, $valid): void {
    $yaml = str_replace("  loras: []\n", "  loras: []\n  unused: []\n", $valid);
    $config = $loader->load($yaml);
    check($config['lookupKeys'] === ['loras'], 'Selection expanded.');
});

test('multiple categories preserve selection order', function () use ($loader): void {
    $config = $loader->load("lookupKeys: [vae, loras]\nsource: {loras: [], vae: []}\ndestination: {loras: /tmp/loras, vae: /tmp/vae}\n");
    check($config['lookupKeys'] === ['vae', 'loras'], 'Selection order changed.');
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
    $group = $config['source']['loras'][0];
    check($group['meta']['enabled'] === false, 'Boolean changed.');
    check($group['weightsList'] === ['001.safetensors', '2026-10-30'], 'Quoted strings changed.');
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
    check($loader->load($indented) === $loader->load($indentless), 'Equivalent sequences parsed differently.');
});

test('syntax error includes a line number', function () use ($loader): void {
    reject($loader, "lookupKeys: [loras\n", 'line');
});

test('distributed example loads', function () use ($loader): void {
    $config = $loader->load(file_get_contents(__DIR__ . '/../config/example.yaml'));
    check(count($config['lookupKeys']) === 6, 'Example categories missing.');
    check(count($config['source']['checkpoint']) === 3, 'Example groups missing.');
});

test('README configuration example loads', function () use ($loader): void {
    $readme = file_get_contents(__DIR__ . '/../README.md');
    check(preg_match('/```yaml\R(.*?)```/s', $readme, $matches) === 1, 'README YAML missing.');
    check($loader->load($matches[1])['lookupKeys'] === ['checkpoint'], 'README selection changed.');
});

function runCommand(string $root, string $command, string $yaml): array
{
    $process = proc_open([PHP_BINARY, "$root/bin/$command"], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, $root);
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
mkdir($root . '/bin', 0700, true);
symlink(realpath(__DIR__ . '/../vendor'), $root . '/vendor');
foreach (['sync', 'clear', 'common.php'] as $file) {
    copy(__DIR__ . '/../bin/' . $file, "$root/bin/$file");
}

try {
    foreach (['sync', 'clear'] as $command) {
        test("$command rejects invalid configuration before DB initialization", function () use ($root, $command, $invalid): void {
            foreach ($invalid as $name => [$yaml, $message]) {
                [$status, $stdout, $stderr] = runCommand($root, $command, $yaml);
                check($status === 1, "$name: expected exit 1, received $status");
                check($stdout === '', "$name: unexpected success output");
                check(str_contains($stderr, 'Configuration error: '), "$name: missing configuration diagnostic");
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
            check(str_contains($stdout, 'newly linked'), 'Result summary missing.');
            $db = new PDO('sqlite:' . $root . '/data/links.sqlite');
            check((int) $db->query('SELECT COUNT(*) FROM links')->fetchColumn() === 0, 'Unexpected link records.');
        });
    }
} finally {
    removeTemporaryDirectory($root);
}

echo "$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
