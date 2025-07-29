<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

class RecordExistsWithoutLinkState implements SyncState
{
    public function __construct(
        private Logger $logger,
        private Linker $linker,
        private string $source,
        private string $target,
        private Counter $counter
    ) {}

    public function shouldSync(): bool
    {
        $this->logger->debug("record exists but symlink missing", ['source' => $this->source, 'target' => $this->target]);
        $this->counter->increment('inconsistent');
        return true;
    }

    public function sync(): void
    {
        if ($this->linker->link($this->source, $this->target) === LinkResult::ACCEPT) {
            $this->logger->debug("link created", ['source' => $this->source, 'target' => $this->target]);
            $recordResult = $this->linker->createRecord($this->source, $this->target);
            if ($recordResult === RecordResult::NEWLY_LINKED) {
                $this->logger->debug("successfully linked", ['source' => $this->source, 'target' => $this->target]);
                $this->counter->increment('newlyLinked');
                $this->counter->increment('alreadyLinked');
            } else {
                $this->logger->error("record creation failed", ['source' => $this->source, 'target' => $this->target]);
                $this->counter->increment('error');
            }
        } else {
            $this->logger->error("symlink creation failed", ['source' => $this->source, 'target' => $this->target]);
            $this->counter->increment('error');
        }
    }
}