<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

final class PlannedOperation
{
    public function __construct(
        public readonly OperationType $type,
        public readonly ?string $source,
        public readonly string $target,
        public readonly OperationAction $action,
        public readonly OperationSnapshot $snapshot
    ) {
    }

    public function description(): string
    {
        return $this->type === OperationType::LINK
            ? "link {$this->source} -> {$this->target} ({$this->action->value})"
            : "unlink {$this->target} ({$this->action->value})";
    }
}
