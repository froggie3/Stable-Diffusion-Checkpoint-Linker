<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Link;

interface UnlinkerInterface
{
    public function unlink(string $path): bool;
}
