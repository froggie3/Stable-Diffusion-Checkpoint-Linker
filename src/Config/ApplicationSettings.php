<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Config;

final class ApplicationSettings
{
    public function __construct(
        public readonly string $configDirectory,
        public readonly string $databasePath
    ) {
    }
}
