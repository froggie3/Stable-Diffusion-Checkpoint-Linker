<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

final class CliOptions
{
    /** @param list<string>|null $categories */
    public function __construct(
        public readonly bool $help = false,
        public readonly bool $dryRun = false,
        public readonly bool $verbose = false,
        public readonly ?array $categories = null,
        public readonly ?string $error = null
    ) {
    }
}
