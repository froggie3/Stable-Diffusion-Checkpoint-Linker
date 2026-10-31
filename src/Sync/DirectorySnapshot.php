<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

final class DirectorySnapshot
{
    public function __construct(
        public readonly ?string $canonical,
        public readonly int|string|null $device,
        public readonly int|string|null $inode,
        public readonly bool $isDirectory
    ) {
    }
}
