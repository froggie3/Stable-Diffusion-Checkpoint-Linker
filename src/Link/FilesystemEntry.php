<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Link;

final class FilesystemEntry
{
    public function __construct(
        public readonly string $kind,
        public readonly ?string $linkTarget = null
    ) {
    }

    public function exists(): bool
    {
        return $this->kind !== 'absent';
    }
}
