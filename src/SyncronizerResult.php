<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

final class SyncronizerResult
{
    public function __construct(protected int $newlyLinkedCount = 0, protected int $notLinkedCount = 0, protected int $removedCount = 0, protected int $loadedCount = 0, protected int $inDisabledCount = 0) {}

    public function print(): void
    {
        printf("%15s%10s%10s%10s%15s\n", "newly linked", "error", "removed", "loaded", "in disabled");
        printf("%15d%10d%10d%10d%15d\n", $this->newlyLinkedCount, $this->notLinkedCount, $this->removedCount, $this->loadedCount, $this->inDisabledCount,);
    }
}
