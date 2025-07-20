<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

final class Unlinker
{
    private Logger $logger;
    private LinkRecordRepository $repository;

    public function __construct(Logger $logger, LinkRecordRepository $repository)
    {
        $this->logger = $logger;
        $this->repository = $repository;
    }

    public function unlink(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $unlinkResult = @unlink($path);
        if (! $unlinkResult) {
            $this->logger->error("failed to unlink file", ['path' => $path]);
            return false;
        }

        $recordDeleted = $this->repository->deleteRecord($path);
        if ($recordDeleted) {
            $this->logger->debug("successfully unlinked", ['path' => $path]);
            return true;
        }

        $this->logger->warning("file was unlinked but record not removed", ['path' => $path]);
        return false;
    }
}
