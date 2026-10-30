<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync\Operation;

interface Operation
{
    public function execute(): void;
}
