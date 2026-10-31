<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Config;

final class ModelGroup
{
    /** @param list<string> $weightsList @param list<string> $ignoreList */
    public function __construct(
        public readonly bool $enabled,
        public readonly string $baseDirectory,
        public readonly array $weightsList,
        public readonly array $ignoreList = []
    ) {
    }
}
