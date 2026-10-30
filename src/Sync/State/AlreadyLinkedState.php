<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync\State;

use Iigau\StableDiffusionCheckpointLinker\Sync\Counter;

use Monolog\Logger;

final class AlreadyLinkedState implements SyncState
{
    public function __construct(private Logger $logger, private string $source, private string $target, private Counter $counter) {}

    public function shouldSync(): bool
    {
        $this->logger->debug("already linked", ['source' => $this->source, 'target' => $this->target]);
        $this->counter->increment('alreadyLinked');
        return false;
    }

    public function sync(): void {}
}