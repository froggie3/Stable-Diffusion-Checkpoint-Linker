<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

interface Operation
{
    public function execute(): void;
}
