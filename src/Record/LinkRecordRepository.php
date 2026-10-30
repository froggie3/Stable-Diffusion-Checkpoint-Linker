<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Record;

interface LinkRecordRepository
{
    public function hasRecord(string $target): bool;

    public function getSource(string $target): ?string;

    public function upsert(string $source, string $target): void;

    public function deleteRecord(string $target): bool;

    public function activateForWrites(): void;
}
