<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

interface SyncState
{
    public function shouldSync(): bool;
    public function sync(): void;
}