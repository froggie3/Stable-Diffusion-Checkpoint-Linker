<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Link;

use RuntimeException;

final class Linker implements LinkerInterface
{
    public function inspect(string $path): FilesystemEntry
    {
        clearstatcache(true, $path);
        if (is_link($path)) {
            $target = readlink($path);
            return new FilesystemEntry('symlink', $target === false ? null : $target);
        }
        if (is_file($path)) {
            return new FilesystemEntry('file');
        }
        if (is_dir($path)) {
            return new FilesystemEntry('directory');
        }
        if (file_exists($path)) {
            return new FilesystemEntry('other');
        }
        return new FilesystemEntry('absent');
    }

    public function createLink(string $source, string $target): void
    {
        if (!is_file($source)) {
            throw new RuntimeException("Link source is missing or not a file: $source");
        }
        if (!@symlink($source, $target)) {
            throw new RuntimeException("Could not create symlink: $target");
        }
    }

    public function removeLink(string $target): void
    {
        if (!is_link($target)) {
            throw new RuntimeException("Refusing to remove a non-symlink filesystem entry: $target");
        }
        if (!@unlink($target)) {
            throw new RuntimeException("Could not remove filesystem entry: $target");
        }
    }
}
