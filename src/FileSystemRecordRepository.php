<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

final class FileSystemRecordRepository implements LinkRecordRepository
{
    public function hasRecord(string $target): bool
    {
        $filename = basename($target);
        $lockfile = __DIR__ . "/../.cache/$filename.lock";
        return file_exists($lockfile);
    }

    public function createRecord(string $source, string $target): bool
    {
        $filename = basename($target);
        $lockfile = __DIR__ . "/../.cache/$filename.lock";
        return @touch($lockfile);
    }

    public function deleteRecord(string $target): bool
    {
        $filename = basename($target);
        $lockfile = __DIR__ . "/../.cache/$filename.lock";

        return file_exists($lockfile) ? @unlink($lockfile) : true;
    }
}
