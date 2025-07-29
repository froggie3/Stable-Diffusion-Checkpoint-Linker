<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

final class SourceNotFoundState implements SyncState
{
    public function __construct(private Logger $logger, private string $source, private Counter $counter) {}

    public function shouldSync(): bool
    {
        $this->logger->error("source not found", ['source' => $this->source]);
        $this->counter->increment('error');
        return false;
    }

    public function sync(): void {}
}