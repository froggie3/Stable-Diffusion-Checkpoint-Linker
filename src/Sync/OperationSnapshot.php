<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

use Iigau\StableDiffusionCheckpointLinker\Link\FilesystemKind;

final class OperationSnapshot
{
    public function __construct(
        public readonly FilesystemKind $kind,
        public readonly ?string $linkTarget,
        public readonly ?string $recordSource,
        public readonly DirectorySnapshot $parent,
        public readonly ?string $rootCanonical,
        public readonly string $rootPath
    ) {
    }
}
