<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directories = ['src', 'tests', 'config', 'scripts'];
$files = [$root . '/.php-cs-fixer.dist.php', $root . '/linker'];

foreach ($directories as $directory) {
    $path = $root . '/' . $directory;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
            $files[] = $file->getPathname();
        }
    }
}

$files = array_values(array_unique($files));
sort($files);

$failed = false;
foreach ($files as $file) {
    $process = proc_open(
        [PHP_BINARY, '-l', $file],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );
    if (!is_resource($process)) {
        fwrite(STDERR, "Could not start PHP syntax check for $file\n");
        $failed = true;
        continue;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $status = proc_close($process);
    if ($status !== 0) {
        fwrite(STDERR, "PHP syntax check failed for $file\n$stdout$stderr");
        $failed = true;
    }
}

exit($failed ? 1 : 0);
