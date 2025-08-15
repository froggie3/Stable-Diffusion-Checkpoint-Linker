<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

interface UnlinkerInterface
{
    public function unlink(string $path): bool;
}
