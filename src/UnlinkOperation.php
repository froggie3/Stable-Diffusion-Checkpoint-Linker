<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

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
