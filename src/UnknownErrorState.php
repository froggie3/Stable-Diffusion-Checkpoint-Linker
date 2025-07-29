<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

final class UnknownErrorState implements SyncState
{
    public function __construct(private Logger $logger, private string $source, private string $target, private Counter $counter) {}

    public function shouldSync(): bool
    {
        $this->logger->error("unclassified error", ['source' => $this->source, 'target' => $this->target]);
        $this->counter->increment('error');
        return false;
    }

    public function sync(): void {}
}