<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

final class TargetExistsWithoutRecordState implements SyncState
{
    public function __construct(private Logger $logger, private string $source, private string $target, private Counter $counter, private Unlinker $unlinker) {}

    public function shouldSync(): bool
    {
        $this->logger->warning("symlink exists but record missing", ['source' => $this->source, 'target' => $this->target]);
        $this->counter->increment('inconsistent');
        return true;
    }

    public function sync(): void
    {
        if ($this->unlinker->unlink($this->target)) {
            $this->logger->debug("unlinked orphan", ['target']);
            $this->counter->increment('removed');
        } else {
            $this->logger->error("unlink failed", ['target']);
            $this->counter->increment('error');
        }
    }
}