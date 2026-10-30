<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

final class PlannedOperation
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $source,
        public readonly string $target,
        public readonly string $action,
        public readonly array $snapshot
    ) {}

    public function description(): string
    {
        return $this->type === 'link'
            ? "link {$this->source} -> {$this->target} ({$this->action})"
            : "unlink {$this->target} ({$this->action})";
    }
}
