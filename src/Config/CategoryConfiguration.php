<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Config;

final class CategoryConfiguration
{
    /** @param list<ModelGroup> $groups */
    public function __construct(
        public readonly string $name,
        public readonly string $destination,
        public readonly array $groups
    ) {
    }
}
