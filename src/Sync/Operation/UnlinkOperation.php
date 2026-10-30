<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync\Operation;

use Iigau\StableDiffusionCheckpointLinker\Link\Unlinker;
use Iigau\StableDiffusionCheckpointLinker\Sync\Counter;

final class UnlinkOperation implements Operation
{
    public function __construct(
        private string $path,
        private Unlinker $unlinker,
        private Counter $counter
    ) {}

    public function execute(): void
    {
        if ($this->unlinker->unlink($this->path)) {
            $this->counter->increment('removed');
        }
    }
}
