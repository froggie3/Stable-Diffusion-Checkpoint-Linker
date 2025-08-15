<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

interface LinkerInterface
{
    public function link(string $source, string $target): LinkResult;
}
