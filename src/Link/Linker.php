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
            return new FilesystemEntry(FilesystemKind::SYMLINK, $target === false ? null : $target);
        }
        if (is_file($path)) {
            return new FilesystemEntry(FilesystemKind::FILE);
        }
        if (is_dir($path)) {
            return new FilesystemEntry(FilesystemKind::DIRECTORY);
        }
        if (file_exists($path)) {
            return new FilesystemEntry(FilesystemKind::OTHER);
        }
        return new FilesystemEntry(FilesystemKind::ABSENT);
    }

    public function createLink(string $source, string $target): void
    {
        if (!is_file($source)) {
            throw new RuntimeException("link source is missing or not a file: $source");
        }
        if (!@symlink($source, $target)) {
            throw new RuntimeException("could not create symlink: $target");
        }
    }

    public function removeLink(string $target): void
    {
        if (!is_link($target)) {
            throw new RuntimeException("refusing to remove a non-symlink filesystem entry: $target");
        }
        if (!@unlink($target)) {
            throw new RuntimeException("could not remove filesystem entry: $target");
        }
    }
}
