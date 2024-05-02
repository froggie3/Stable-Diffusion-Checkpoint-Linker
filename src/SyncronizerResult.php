<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

final class SyncronizerResult
{
    protected $newlyLinkedCount = 0;
    protected $inDisabledCount = 0;
    protected $notLinkedCount = 0;
    protected $removedCount = 0;
    protected $loadedCount = 0;

    public function __construct(int $newlyLinkedCount, int $inDisabledCount, int $notLinkedCount, int $removedCount, int $loadedCount)
    {
        $this->newlyLinkedCount = $newlyLinkedCount;
        $this->notLinkedCount = $notLinkedCount;
        $this->removedCount = $removedCount;
        $this->loadedCount = $loadedCount;
        $this->inDisabledCount = $inDisabledCount;
    }

    public function print(): void
    {
        printf("%15s%10s%10s%10s%15s\n", "newly linked", "error", "removed", "loaded", "in disabled");
        printf("%15d%10d%10d%10d%15d\n", $this->newlyLinkedCount, $this->notLinkedCount, $this->removedCount, $this->loadedCount, $this->inDisabledCount,);
    }
}
