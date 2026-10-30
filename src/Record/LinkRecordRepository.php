<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Record;

interface LinkRecordRepository
{
    public function hasRecord(string $target): bool;

    public function createRecord(string $source, string $target): bool;

    public function deleteRecord(string $target): bool;
}
