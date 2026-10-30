<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * Run a CLI case in a fresh disposable project. The application derives its
 * project root from bin/{sync,clear}, so data/ always stays inside this tree.
 */
function safetyScenario(callable $callback): void
{
    $root = sys_get_temp_dir() . '/linker-safety-' . bin2hex(random_bytes(8));
    mkdir($root . '/bin', 0700, true);
    symlink(realpath(__DIR__ . '/../vendor'), $root . '/vendor');
    foreach (['sync', 'clear', 'common.php'] as $file) {
        copy(__DIR__ . '/../bin/' . $file, "$root/bin/$file");
    }
    try {
        $callback($root);
    } finally {
        safetyRemoveTree($root);
    }
}

function safetyRemoveTree(string $path): void
{
    if (!is_dir($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (new FilesystemIterator($path) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            safetyRemoveTree($entry->getPathname());
        } else {
            @unlink($entry->getPathname());
        }
    }
    @rmdir($path);
}

/** @return array{0:int,1:string,2:string} */
function safetyRunCli(string $root, string $command, array $arguments = [], ?string $yaml = null): array
{
    $process = proc_open(
        [PHP_BINARY, "$root/bin/$command", ...$arguments],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start isolated CLI process.');
    }
    if ($yaml !== null) {
        fwrite($pipes[0], $yaml);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    clearstatcache(true);
    return [$status, $stdout, $stderr];
}

/** @param array<string,list<array<string,mixed>>> $groups */
function safetyConfig(array $groups, ?array $lookupKeys = null, ?array $destinations = null): string
{
    $keys = $lookupKeys ?? array_keys($groups);
    $destinations ??= array_fill_keys($keys, '/unused/destination');
    return Yaml::dump([
        'lookupKeys' => $keys,
        'source' => $groups,
        'destination' => $destinations,
    ], 12, 2);
}

function safetyGroup(string $base, array $weights, array $ignore = [], bool $enabled = true): array
{
    return [[
        'meta' => ['enabled' => $enabled],
        'baseDirectory' => $base,
        'weightsList' => $weights,
        'ignoreList' => $ignore,
    ]];
}

function safetyDb(string $root): PDO
{
    return new PDO('sqlite:' . $root . '/data/links.sqlite');
}

function safetyDbExists(string $root): bool
{
    return file_exists($root . '/data/links.sqlite');
}

function safetyEnsureDb(string $root): PDO
{
    mkdir($root . '/data', 0700, true);
    $db = safetyDb($root);
    $db->exec('CREATE TABLE links (source TEXT NOT NULL, target TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL, PRIMARY KEY(source, target))');
    return $db;
}

function safetySeed(PDO $db, string $source, string $target): void
{
    $stmt = $db->prepare('INSERT INTO links (source, target, created_at) VALUES (?, ?, ?)');
    $stmt->execute([$source, $target, '2026-01-01T00:00:00+00:00']);
}

function safetyRecords(PDO $db): array
{
    return $db->query('SELECT target, source FROM links ORDER BY target')->fetchAll(PDO::FETCH_KEY_PAIR);
}

function safetyAssertStatus(array $result, int $expected, string $context): void
{
    check($result[0] === $expected, "$context: expected exit $expected, received {$result[0]}; {$result[2]}");
}

$catPath = static fn (string $root, string $cat = 'models'): string => "$root/destination/$cat";
$targetPath = static fn (string $root, string $name, string $cat = 'models'): string => "$root/destination/$cat/$name";
$sourcePath = static fn (string $root, string $dir, string $name): string => "$root/$dir/$name";

test('safety CLI initializes data and sync is idempotent', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'source', 'a.bin'), 'source payload');
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['a.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 0, 'first sync');
        check(safetyDbExists($root), 'First sync did not initialize data/links.sqlite.');
        check(is_link($targetPath($root, 'a.bin')), 'First sync did not create a symbolic link.');
        check(readlink($targetPath($root, 'a.bin')) === $sourcePath($root, 'source', 'a.bin'), 'Link target is wrong.');
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 0, 'repeat sync');
        check(count(safetyRecords(safetyDb($root))) === 1, 'Repeat sync duplicated the managed record.');
    });
});

test('sync changes a recorded source once when baseDirectory changes', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/old', 0700, true);
        mkdir($root . '/new', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'old', 'a.bin'), 'old');
        file_put_contents($sourcePath($root, 'new', 'a.bin'), 'new');
        $target = $targetPath($root, 'a.bin');
        safetySeed(safetyEnsureDb($root), $sourcePath($root, 'old', 'a.bin'), $target);
        symlink($sourcePath($root, 'old', 'a.bin'), $target);
        $yaml = safetyConfig(['models' => safetyGroup($root . '/new', ['a.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 0, 'source change sync');
        check(readlink($target) === $sourcePath($root, 'new', 'a.bin'), 'Managed link was not redirected to the new source.');
        check(array_values(safetyRecords(safetyDb($root))) === [$sourcePath($root, 'new', 'a.bin')], 'DB source was not updated.');
    });
});

test('sync repairs a missing managed link and keeps one DB record', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'source', 'a.bin'), 'a');
        $target = $targetPath($root, 'a.bin');
        safetySeed(safetyEnsureDb($root), $sourcePath($root, 'source', 'a.bin'), $target);
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['a.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 0, 'repair missing target');
        check(is_link($target) && count(safetyRecords(safetyDb($root))) === 1, 'Missing link was not repaired idempotently.');
    });
});

test('sync adopts a correct unmanaged link', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'source', 'a.bin'), 'a');
        $target = $targetPath($root, 'a.bin');
        symlink($sourcePath($root, 'source', 'a.bin'), $target);
        $before = lstat($target)['ino'];
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['a.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 0, 'adopt correct link');
        check(readlink($target) === $sourcePath($root, 'source', 'a.bin') && lstat($target)['ino'] === $before, 'Correct unmanaged link was replaced.');
        check(count(safetyRecords(safetyDb($root))) === 1, 'Correct unmanaged link was not registered.');
    });
});

test('sync adopts relative link whose symlink component precedes dot-dot', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source/sub', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'source', 'a.bin'), 'a');
        symlink($root . '/source/sub', $root . '/alias');
        $target = $targetPath($root, 'a.bin');
        symlink('../../alias/../a.bin', $target);
        $before = lstat($target)['ino'];
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['a.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 0, 'relative symlink adoption');
        check(readlink($target) === '../../alias/../a.bin' && lstat($target)['ino'] === $before, 'Relative link spelling or symlink inode changed.');
        check(count(safetyRecords(safetyDb($root))) === 1, 'Relative unmanaged link was not adopted.');
    });
});

test('sync and clear preserve an unmanaged link to a different source', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($root . '/other', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'source', 'a.bin'), 'desired');
        file_put_contents($sourcePath($root, 'other', 'a.bin'), 'actual');
        $target = $targetPath($root, 'a.bin');
        symlink($sourcePath($root, 'other', 'a.bin'), $target);
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['a.bin'])], null, ['models' => $catPath($root)]);
        foreach (['sync', 'clear'] as $cmd) {
            safetyAssertStatus(safetyRunCli($root, $cmd, [], $yaml), 1, "unmanaged differing link $cmd");
            check(readlink($target) === $sourcePath($root, 'other', 'a.bin'), "$cmd changed an unmanaged link.");
            check(!safetyDbExists($root), "$cmd initialized DB for rejected unmanaged link.");
        }
    });
});

test('a manually changed managed link is rejected even when it matches desired source', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/old', 0700, true);
        mkdir($root . '/desired', 0700, true);
        mkdir($catPath($root), 0700, true);
        foreach (['old', 'desired'] as $d) {
            file_put_contents($sourcePath($root, $d, 'a.bin'), $d);
        }
        $target = $targetPath($root, 'a.bin');
        safetySeed(safetyEnsureDb($root), $sourcePath($root, 'old', 'a.bin'), $target);
        symlink($sourcePath($root, 'desired', 'a.bin'), $target);
        $yaml = safetyConfig(['models' => safetyGroup($root . '/desired', ['a.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 1, 'manual managed-link edit');
        check(readlink($target) === $sourcePath($root, 'desired', 'a.bin'), 'Rejected manual edit was changed.');
        check(array_values(safetyRecords(safetyDb($root))) === [$sourcePath($root, 'old', 'a.bin')], 'Rejected manual edit changed DB.');
    });
});

test('ordinary file and directory targets are protected by sync and clear', function () use ($catPath, $targetPath, $sourcePath): void {
    foreach (['sync', 'clear'] as $cmd) {
        safetyScenario(function (string $root) use ($cmd, $catPath, $targetPath, $sourcePath): void {
            mkdir($root . '/source', 0700, true);
            mkdir($catPath($root), 0700, true);
            foreach (['file.bin', 'dir.bin'] as $name) {
                file_put_contents($sourcePath($root, 'source', $name), 'source');
            }
            file_put_contents($targetPath($root, 'file.bin'), 'precious');
            mkdir($targetPath($root, 'dir.bin'));
            $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['file.bin', 'dir.bin'])], null, ['models' => $catPath($root)]);
            safetyAssertStatus(safetyRunCli($root, $cmd, [], $yaml), 1, "ordinary target collision $cmd");
            check(file_get_contents($targetPath($root, 'file.bin')) === 'precious' && is_dir($targetPath($root, 'dir.bin')), "$cmd altered an ordinary target.");
            check(!safetyDbExists($root), "$cmd created DB before ordinary target collision was rejected.");
        });
    }
});

test('clear removes managed dangling link without requiring source', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($catPath($root), 0700, true);
        $target = $targetPath($root, 'gone.bin');
        safetySeed(safetyEnsureDb($root), $sourcePath($root, 'missing', 'gone.bin'), $target);
        symlink($sourcePath($root, 'missing', 'gone.bin'), $target);
        $yaml = safetyConfig(['models' => safetyGroup($root . '/missing', ['gone.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'clear', [], $yaml), 0, 'clear managed dangling link');
        check(!file_exists($target) && !is_link($target) && safetyRecords(safetyDb($root)) === [], 'Managed dangling link/record remain after clear.');
    });
});

test('clear recognizes managed relative dangling link by resolved spelling', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source/sub', 0700, true);
        mkdir($catPath($root), 0700, true);
        symlink($root . '/source/sub', $root . '/alias');
        $target = $targetPath($root, 'gone.bin');
        symlink('../../alias/../gone.bin', $target);
        $db = safetyEnsureDb($root);
        safetySeed($db, $sourcePath($root, 'source', 'gone.bin'), $target);
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['gone.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'clear', [], $yaml), 0, 'relative dangling link clear');
        check(!file_exists($target) && !is_link($target) && safetyRecords(safetyDb($root)) === [], 'Managed relative dangling link was not cleared.');
    });
});

test('clear refuses an unmanaged dangling link', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($catPath($root), 0700, true);
        $target = $targetPath($root, 'gone.bin');
        symlink($sourcePath($root, 'missing', 'gone.bin'), $target);
        $yaml = safetyConfig(['models' => safetyGroup($root . '/missing', ['gone.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'clear', [], $yaml), 1, 'clear unmanaged dangling link');
        check(is_link($target) && !safetyDbExists($root), 'Rejected dangling link changed or initialized DB.');
    });
});

test('clear deletes a management record when target is already absent', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($catPath($root), 0700, true);
        $db = safetyEnsureDb($root);
        safetySeed($db, $sourcePath($root, 'missing', 'a.bin'), $targetPath($root, 'a.bin'));
        $yaml = safetyConfig(['models' => safetyGroup($root . '/missing', ['a.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'clear', [], $yaml), 0, 'clear absent target');
        check(safetyRecords(safetyDb($root)) === [], 'Record-only target was not cleared.');
    });
});

test('sync unlinks disabled weights and enabled ignoreList entries', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        $names = ['disabled.bin', 'ignored.bin', 'active.bin'];
        foreach ($names as $n) {
            file_put_contents($sourcePath($root, 'source', $n), $n);
        }
        $db = safetyEnsureDb($root);
        foreach ($names as $n) {
            $t = $targetPath($root, $n);
            symlink($sourcePath($root, 'source', $n), $t);
            safetySeed($db, $sourcePath($root, 'source', $n), $t);
        }
        $groups = ['models' => [
            ['meta' => ['enabled' => false], 'baseDirectory' => $root . '/source', 'weightsList' => ['disabled.bin'], 'ignoreList' => []],
            ['meta' => ['enabled' => true], 'baseDirectory' => $root . '/source', 'weightsList' => ['active.bin'], 'ignoreList' => ['ignored.bin']],
        ]];
        $yaml = safetyConfig($groups, null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 0, 'sync enabled/ignore rules');
        check(!is_link($targetPath($root, 'disabled.bin')) && !is_link($targetPath($root, 'ignored.bin')) && is_link($targetPath($root, 'active.bin')), 'Sync did not apply enabled/ignore rules.');
    });
});

test('sync leaves disabled-group ignoreList outside operation selection', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'source', 'keep.bin'), 'k');
        symlink($sourcePath($root, 'source', 'keep.bin'), $targetPath($root, 'keep.bin'));
        $db = safetyEnsureDb($root);
        safetySeed($db, $sourcePath($root, 'source', 'keep.bin'), $targetPath($root, 'keep.bin'));
        $group = [['meta' => ['enabled' => false], 'baseDirectory' => $root . '/source', 'weightsList' => [], 'ignoreList' => ['keep.bin']]];
        $yaml = safetyConfig(['models' => $group], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 0, 'disabled ignoreList');
        check(is_link($targetPath($root, 'keep.bin')), 'Disabled group ignoreList was unexpectedly cleared.');
    });
});

test('clear applies to weights and ignoreList regardless of enabled state', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        $db = safetyEnsureDb($root);
        $names = ['weight.bin', 'ignored.bin'];
        foreach ($names as $n) {
            file_put_contents($sourcePath($root, 'source', $n), $n);
            symlink($sourcePath($root, 'source', $n), $targetPath($root, $n));
            safetySeed($db, $sourcePath($root, 'source', $n), $targetPath($root, $n));
        }
        $group = [['meta' => ['enabled' => false], 'baseDirectory' => $root . '/source', 'weightsList' => ['weight.bin'], 'ignoreList' => ['ignored.bin']]];
        $yaml = safetyConfig(['models' => $group], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'clear', [], $yaml), 0, 'clear all configured lists');
        check(!is_link($targetPath($root, 'weight.bin')) && !is_link($targetPath($root, 'ignored.bin')) && safetyRecords(safetyDb($root)) === [], 'Clear did not remove every configured record/link.');
    });
});

test('sync and clear preserve source files', function () use ($catPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        $src = $sourcePath($root, 'source', 'a.bin');
        file_put_contents($src, 'stay');
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['a.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 0, 'source retention sync');
        safetyAssertStatus(safetyRunCli($root, 'clear', [], $yaml), 0, 'source retention clear');
        check(is_file($src) && file_get_contents($src) === 'stay', 'Source file was removed or modified.');
    });
});

test('late source-missing item preflight makes no earlier link or DB change', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'source', 'a.bin'), 'a');
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['a.bin', 'missing.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 1, 'late missing source');
        check(!file_exists($targetPath($root, 'a.bin')) && !safetyDbExists($root), 'Preflight failure changed earlier target or initialized DB.');
    });
});

test('late target collision preflight makes no earlier link or DB change', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'source', 'a.bin'), 'a');
        file_put_contents($sourcePath($root, 'source', 'b.bin'), 'b');
        file_put_contents($targetPath($root, 'b.bin'), 'precious');
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['a.bin', 'b.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 1, 'late collision');
        check(!file_exists($targetPath($root, 'a.bin')) && file_get_contents($targetPath($root, 'b.bin')) === 'precious' && !safetyDbExists($root), 'Preflight collision changed targets or created DB.');
    });
});

test('same destination competing sources are detected independent of order', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/one', 0700, true);
        mkdir($root . '/two', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'one', 'same.bin'), '1');
        file_put_contents($sourcePath($root, 'two', 'same.bin'), '2');
        $g1 = safetyGroup($root . '/one', ['same.bin'])[0];
        $g2 = safetyGroup($root . '/two', ['same.bin'])[0];
        foreach ([[$g1, $g2], [$g2, $g1]] as $groups) {
            $yaml = safetyConfig(['models' => $groups], null, ['models' => $catPath($root)]);
            safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 1, 'competing destination');
            check(!file_exists($targetPath($root, 'same.bin')) && !safetyDbExists($root), 'Competing destination caused order-dependent mutation.');
        }
    });
});

test('same destination create and delete operations conflict independent of order', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/one', 0700, true);
        mkdir($root . '/two', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'one', 'same.bin'), '1');
        $create = safetyGroup($root . '/one', ['same.bin'])[0];
        $delete = ['meta' => ['enabled' => true], 'baseDirectory' => $root . '/two', 'weightsList' => [], 'ignoreList' => ['same.bin']];
        foreach ([[$create, $delete], [$delete, $create]] as $groups) {
            $yaml = safetyConfig(['models' => $groups], null, ['models' => $catPath($root)]);
            safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 1, 'competing create/delete destination');
            check(!file_exists($targetPath($root, 'same.bin')) && !safetyDbExists($root), 'Create/delete conflict caused order-dependent mutation.');
        }
    });
});

test('unsafe weight traversal and absolute weight paths are rejected', function () use ($catPath, $targetPath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        mkdir($root . '/outside', 0700, true);
        file_put_contents($root . '/outside/escape.bin', 'x');
        foreach (['../outside/escape.bin', $root . '/outside/escape.bin'] as $weight) {
            $yaml = safetyConfig(['models' => safetyGroup($root . '/source', [$weight])], null, ['models' => $catPath($root)]);
            safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 1, 'unsafe weight path');
            check(!safetyDbExists($root) && !file_exists($targetPath($root, basename($weight))), 'Unsafe weight path mutated target or initialized DB.');
        }
    });
});

test('destination parent symlink cannot escape its configured root', function () use ($catPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($root . '/outside', 0700, true);
        mkdir($catPath($root), 0700, true);
        symlink($root . '/outside', $catPath($root) . '/escaped');
        file_put_contents($sourcePath($root, 'source', 'a.bin'), 'a');
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['escaped/a.bin'])], null, ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 1, 'destination parent symlink escape');
        check(!file_exists($root . '/outside/a.bin') && !safetyDbExists($root), 'Symlink escape created target or initialized DB.');
    });
});

test('destination itself may be a symlink to its real directory for sync and clear', function () use ($sourcePath): void {
    safetyScenario(function (string $root) use ($sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($root . '/destination/real', 0700, true);
        symlink($root . '/destination/real', $root . '/destination/alias');
        file_put_contents($sourcePath($root, 'source', 'a.bin'), 'a');
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['a.bin'])], null, ['models' => $root . '/destination/alias']);
        $target = $root . '/destination/real/a.bin';
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 0, 'sync through destination symlink');
        check(is_link($target) && readlink($target) === $sourcePath($root, 'source', 'a.bin'), 'Sync through destination symlink did not create the link.');
        check(count(safetyRecords(safetyDb($root))) === 1, 'Sync through destination symlink did not record the target.');
        safetyAssertStatus(safetyRunCli($root, 'clear', [], $yaml), 0, 'clear through destination symlink');
        check(!file_exists($target) && !is_link($target) && safetyRecords(safetyDb($root)) === [], 'Clear through destination symlink left a link or DB record: ' . var_export([is_link($target), safetyRecords(safetyDb($root))], true));
    });
});

test('real destination and symlink alias conflict on the same target before mutation', function (): void {
    safetyScenario(function (string $root): void {
        mkdir($root . '/one', 0700, true);
        mkdir($root . '/two', 0700, true);
        mkdir($root . '/destination/real', 0700, true);
        symlink($root . '/destination/real', $root . '/destination/alias');
        file_put_contents($root . '/one/same.bin', 'one');
        file_put_contents($root . '/two/same.bin', 'two');
        $groups = [
            'one' => safetyGroup($root . '/one', ['same.bin']),
            'two' => safetyGroup($root . '/two', ['same.bin']),
        ];
        $destinations = ['one' => $root . '/destination/real', 'two' => $root . '/destination/alias'];
        foreach ([['one', 'two'], ['two', 'one']] as $keys) {
            $yaml = safetyConfig($groups, $keys, $destinations);
            safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 1, 'real/alias target collision');
            check(!file_exists($root . '/destination/real/same.bin') && !safetyDbExists($root), 'Real/alias collision mutated target or initialized DB.');
        }
    });
});

test('sync rejects source path that is the existing target symlink itself', function () use ($targetPath): void {
    safetyScenario(function (string $root) use ($targetPath): void {
        mkdir($root . '/destination/models', 0700, true);
        mkdir($root . '/outside', 0700, true);
        file_put_contents($root . '/outside/a.bin', 'external');
        $target = $targetPath($root, 'a.bin');
        symlink($root . '/outside/a.bin', $target);
        $inode = lstat($target)['ino'];
        $yaml = safetyConfig(['models' => safetyGroup($root . '/destination/models', ['a.bin'])], null, ['models' => $root . '/destination/models']);
        safetyAssertStatus(safetyRunCli($root, 'sync', [], $yaml), 1, 'self-referential source target');
        check(is_link($target) && readlink($target) === $root . '/outside/a.bin' && lstat($target)['ino'] === $inode, 'Rejected self-referential symlink was altered.');
        check(!safetyDbExists($root), 'Self-referential symlink was registered in a new DB.');
    });
});

test('--help succeeds without input and does not initialize DB', function (): void {
    safetyScenario(function (string $root): void {
        safetyAssertStatus(safetyRunCli($root, 'sync', ['--help']), 0, 'help');
        check(!safetyDbExists($root), '--help initialized the DB.');
    });
});

test('invalid CLI option exits with an error without DB initialization', function (): void {
    safetyScenario(function (string $root): void {
        safetyAssertStatus(safetyRunCli($root, 'sync', ['--unknown']), 2, 'invalid option');
        check(!safetyDbExists($root), 'Invalid option initialized the DB.');
    });
});

test('--dry-run plans initial links without creating DB or targets', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'source', 'a.bin'), 'a');
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['a.bin'])], null, ['models' => $catPath($root)]);
        [$status, $stdout, $stderr] = safetyRunCli($root, 'sync', ['--dry-run'], $yaml);
        safetyAssertStatus([$status, $stdout, $stderr], 0, 'dry run new database');
        check(!safetyDbExists($root) && !file_exists($targetPath($root, 'a.bin')), 'New-database dry run mutated state.');
        check(str_contains($stdout . $stderr, 'a.bin'), 'Dry-run output did not identify planned item.');
    });
});

test('--dry-run leaves existing DB bytes unchanged and shows repair/clear plans', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'source', 'repair.bin'), 'r');
        $db = safetyEnsureDb($root);
        safetySeed($db, $sourcePath($root, 'source', 'repair.bin'), $targetPath($root, 'repair.bin'));
        $before = hash_file('sha256', $root . '/data/links.sqlite');
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['repair.bin'])], null, ['models' => $catPath($root)]);
        foreach ([['sync', ['--dry-run']], ['clear', ['--dry-run']]] as [$cmd, $args]) {
            [$status, $stdout, $stderr] = safetyRunCli($root, $cmd, $args, $yaml);
            safetyAssertStatus([$status, $stdout, $stderr], 0, "$cmd dry run existing DB");
            check(hash_file('sha256', $root . '/data/links.sqlite') === $before, "$cmd dry run changed DB bytes.");
        }
        check(!file_exists($targetPath($root, 'repair.bin')), 'Dry runs changed targets.');
    });
});

test('--dry-run shows unmanaged-link adoption without DB or link mutation', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'source', 'adopt.bin'), 'a');
        $target = $targetPath($root, 'adopt.bin');
        symlink($sourcePath($root, 'source', 'adopt.bin'), $target);
        $inode = lstat($target)['ino'];
        $db = safetyEnsureDb($root);
        $before = hash_file('sha256', $root . '/data/links.sqlite');
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['adopt.bin'])], null, ['models' => $catPath($root)]);
        [$status, $stdout, $stderr] = safetyRunCli($root, 'sync', ['--dry-run'], $yaml);
        safetyAssertStatus([$status, $stdout, $stderr], 0, 'dry-run unmanaged adoption');
        check(str_contains($stdout . $stderr, 'adopt.bin'), 'Dry-run output did not identify adoption.');
        check(hash_file('sha256', $root . '/data/links.sqlite') === $before && safetyRecords($db) === [], 'Adoption dry run changed DB.');
        check(is_link($target) && lstat($target)['ino'] === $inode, 'Adoption dry run changed symlink.');
    });
});

test('repeatable --category limits to selected lookupKeys categories', function () use ($catPath, $targetPath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath): void {
        foreach (['one', 'two'] as $cat) {
            mkdir($root . "/$cat", 0700, true);
            mkdir($catPath($root, $cat), 0700, true);
            file_put_contents("$root/$cat/$cat.bin", $cat);
        }
        $groups = ['one' => safetyGroup($root . '/one', ['one.bin']), 'two' => safetyGroup($root . '/two', ['two.bin'])];
        $yaml = safetyConfig($groups, ['one', 'two'], ['one' => $catPath($root, 'one'), 'two' => $catPath($root, 'two')]);
        safetyAssertStatus(safetyRunCli($root, 'sync', ['--category', 'one', '--category=two'], $yaml), 0, 'multiple categories');
        check(is_link($targetPath($root, 'one.bin', 'one')) && is_link($targetPath($root, 'two.bin', 'two')), 'Repeatable category options did not select both categories.');
    });
});

test('--category outside lookupKeys is rejected without changes', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/source', 0700, true);
        mkdir($catPath($root), 0700, true);
        file_put_contents($sourcePath($root, 'source', 'a.bin'), 'a');
        $yaml = safetyConfig(['models' => safetyGroup($root . '/source', ['a.bin'])], ['models'], ['models' => $catPath($root)]);
        safetyAssertStatus(safetyRunCli($root, 'sync', ['--category', 'elsewhere'], $yaml), 2, 'category outside lookupKeys');
        check(!file_exists($targetPath($root, 'a.bin')) && !safetyDbExists($root), 'Out-of-range category changed state.');
    });
});

test('category selection excludes invalid unselected source from preflight', function () use ($catPath, $targetPath, $sourcePath): void {
    safetyScenario(function (string $root) use ($catPath, $targetPath, $sourcePath): void {
        mkdir($root . '/good', 0700, true);
        mkdir($catPath($root, 'good'), 0700, true);
        mkdir($catPath($root, 'bad'), 0700, true);
        file_put_contents($sourcePath($root, 'good', 'ok.bin'), 'ok');
        $groups = ['good' => safetyGroup($root . '/good', ['ok.bin']), 'bad' => safetyGroup($root . '/missing', ['missing.bin'])];
        $yaml = safetyConfig($groups, ['good', 'bad'], ['good' => $catPath($root, 'good'), 'bad' => $catPath($root, 'bad')]);
        safetyAssertStatus(safetyRunCli($root, 'sync', ['--category', 'good'], $yaml), 0, 'select good category');
        check(is_link($targetPath($root, 'ok.bin', 'good')) && !file_exists($targetPath($root, 'missing.bin', 'bad')), 'Category filter included an unselected source.');
    });
});

echo "Safety CLI regression cases loaded.\n";
