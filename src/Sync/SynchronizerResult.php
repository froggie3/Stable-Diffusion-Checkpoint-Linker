<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

final class SynchronizerResult
{
    public function __construct(
        public readonly OperationCounts $counts,
        public readonly int $operationCount,
        public readonly int $completedCount,
        public readonly array $completedOperations,
        public readonly ?string $failedOperation,
        public readonly ?string $errorMessage,
        public readonly array $partialEffects,
        public readonly array $unexecutedOperations
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->failedOperation === null;
    }
}
