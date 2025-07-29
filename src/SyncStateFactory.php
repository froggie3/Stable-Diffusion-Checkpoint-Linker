<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

final class SyncStateFactory
{
    public function __construct(
        private Logger $logger,
        private Linker $linker,
        private Unlinker $unlinker,
        private Counter $counter
    ) {}

    public function create(SyncObjectStatus $status, string $source, string $target): SyncState
    {
        return match ($status) {
            SyncObjectStatus::ALREADY_LINKED => new AlreadyLinkedState($this->logger, $source, $target, $this->counter),
            SyncObjectStatus::SOURCE_NOT_FOUND => new SourceNotFoundState($this->logger, $source, $this->counter),
            SyncObjectStatus::RECORD_EXISTS_WITHOUT_LINK => new RecordExistsWithoutLinkState($this->logger, $this->linker, $source, $target, $this->counter),
            SyncObjectStatus::TARGET_ALREADY_EXISTS_WITHOUT_RECORD => new TargetExistsWithoutRecordState($this->logger, $source, $target, $this->counter, $this->unlinker),
            SyncObjectStatus::READY => new ReadyToLinkState($this->logger, $this->linker, $source, $target, $this->counter),
            default => new UnknownErrorState($this->logger, $source, $target, $this->counter),
        };
    }
}