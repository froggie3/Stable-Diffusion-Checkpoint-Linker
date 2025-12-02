<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

final class LinkOperation implements Operation
{
    public function __construct(
        private string $source,
        private string $target,
        private Linker $linker,
        private Counter $counter,
        private SyncStateFactory $factory
    ) {}

    public function execute(): void
    {
        $status = $this->linker->test($this->source, $this->target);
        $state = $this->factory->create($status, $this->source, $this->target);

        if (!$state->shouldSync()) {
            return;
        }

        $state->sync();
    }
}
