<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

final class Linker
{
    protected Logger $logger;
    protected LinkRecordRepository $repository;

    public function __construct(Logger $logger, LinkRecordRepository $repository)
    {
        $this->logger = $logger;
        $this->repository = $repository;
    }

    public function createRecord(string $source, string $target): RecordResult
    {
        try {
            if (!$this->repository->createRecord($source, $target)) {
                return RecordResult::RECORD_CREATION_ERROR;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Record creation error: ' . $e->getMessage());
            return RecordResult::RECORD_CREATION_ERROR;
        }

        return RecordResult::NEWLY_LINKED;
    }

    public function link(string $source, string $target): LinkResult
    {
        if (!@symlink($source, $target)) {
            return LinkResult::SYMLINK_ERROR;
        }

        return LinkResult::ACCEPT;
    }

    public function test(string $source, string $target): SyncObjectStatus
    {
        if (!file_exists($source)) {
            return SyncObjectStatus::SOURCE_NOT_FOUND;
        }

        $targetExists = file_exists($target);
        $recordExists = $this->repository->hasRecord($target);

        if ($targetExists && $recordExists) {
            return SyncObjectStatus::ALREADY_LINKED;
        }

        if ($targetExists && ! $recordExists) {
            return SyncObjectStatus::TARGET_ALREADY_EXISTS_WITHOUT_RECORD;
        }

        if (! $targetExists && $recordExists) {
            return SyncObjectStatus::RECORD_EXISTS_WITHOUT_LINK;
        }

        return SyncObjectStatus::READY;
    }
}
