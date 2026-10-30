<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Link;

interface LinkerInterface
{
    public function inspect(string $path): FilesystemEntry;

    public function createLink(string $source, string $target): void;

    public function removeLink(string $target): void;
}
