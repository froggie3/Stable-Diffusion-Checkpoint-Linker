<?php

declare(strict_types=1);

use Iigau\StableDiffusionCheckpointLinker\Console\SyncClearArgumentParser;

test('sync and clear help do not override invalid options', function (): void {
    safetyScenario(function (string $root): void {
        foreach (['sync', 'clear'] as $command) {
            foreach ([['--help', '--unknown'], ['--unknown', '-h'], ['--help', '--category']] as $args) {
                [$status, $stdout, $stderr] = runCommandWithOpenStdin($root, [$command, ...$args]);
                check($status === 2 && $stdout === '' && str_contains($stderr, 'argument error:'), "$command accepted invalid help arguments: " . implode(' ', $args));
            }
        }
        check(!file_exists($root . '/data'), 'Invalid help initialized the database.');
    });
});

test('--category consumes -v as a category rather than verbose', function (): void {
    $options = (new SyncClearArgumentParser())->parse(['--category', '-v']);
    check($options->error === null && $options->categories === ['-v'] && !$options->verbose, '-v was not consumed as a category.');
    safetyScenario(function (string $root): void {
        $yaml = safetyConfig(['models' => []]);
        foreach (['sync', 'clear'] as $command) {
            [$status, $stdout, $stderr] = runCommand($root, $command, $yaml, ['--category', '-v']);
            check($status === 2 && $stdout === '' && str_contains($stderr, 'category is outside lookupKeys: -v'), "$command did not validate -v as a category: $stderr");
        }
        check(!file_exists($root . '/data'), 'Invalid category initialized the database.');
    });
});

test('-- ends option parsing and remaining arguments are positional', function (): void {
    $parser = new SyncClearArgumentParser();
    $options = $parser->parse(['--dry-run', '--', '--verbose']);
    check($options->error === null && $options->dryRun && !$options->verbose && $options->configFile === '--verbose', 'Post-delimiter option was not a config path.');
    $options = $parser->parse(['--', '--', '-v']);
    check($options->error === 'unexpected positional argument: -v', 'Post-delimiter tokens were not treated as positional.');
    $options = $parser->parse(['--category', '--']);
    check($options->error === '--category requires a category name', 'Delimiter was accepted as a category value.');
});

test('unknown categories are checked after YAML loading in request order', function (): void {
    safetyScenario(function (string $root): void {
        foreach (['sync', 'clear'] as $command) {
            $args = ['--category', 'missing-two', '--category', 'missing-one'];
            [$status, $stdout, $stderr] = runCommand($root, $command, 'invalid: [yaml', $args);
            check($status === 1 && $stdout === '' && str_contains($stderr, 'configuration error:'), "$command checked categories before YAML: $stderr");
            [$status, $stdout, $stderr] = runCommand($root, $command, safetyConfig(['models' => []]), $args);
            check($status === 2 && $stdout === '' && str_contains($stderr, 'category is outside lookupKeys: missing-two'), "$command did not report the first unknown category: $stderr");
        }
        check(!file_exists($root . '/data'), 'Invalid YAML or categories initialized the database.');
    });
});

test('category filtering preserves YAML lookupKeys order and removes duplicates', function (): void {
    safetyScenario(function (string $root): void {
        foreach (['one', 'two'] as $category) {
            mkdir("$root/$category", 0700);
            mkdir("$root/dest-$category", 0700);
            file_put_contents("$root/$category/$category.bin", $category);
        }
        $yaml = safetyConfig(
            ['one' => safetyGroup("$root/one", ['one.bin']), 'two' => safetyGroup("$root/two", ['two.bin'])],
            ['one', 'two'],
            ['one' => "$root/dest-one", 'two' => "$root/dest-two"]
        );
        [$status, $stdout, $stderr] = runCommand($root, 'sync', $yaml, ['--dry-run', '--category', 'two', '--category=one', '--category=two']);
        $one = strpos($stdout, 'one.bin');
        $two = strpos($stdout, 'two.bin');
        check($status === 0 && $stderr === '' && $one !== false && $two !== false && $one < $two, "Selection did not follow lookupKeys order: $stdout $stderr");
        check(substr_count($stdout, 'would link ') === 2, 'Repeated category produced duplicate operations.');
        check(!file_exists($root . '/data'), 'Dry run initialized the database.');
    });
});
