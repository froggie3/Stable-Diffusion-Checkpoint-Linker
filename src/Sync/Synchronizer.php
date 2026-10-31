<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

use Throwable;

final class Synchronizer
{
    public function __construct(
        private OperationPlan $plan,
        private OperationExecutor $executor
    ) {
    }

    public function run(): SynchronizerResult
    {
        $counts = new OperationCounts();
        $completed = [];
        $failedOperation = null;
        $errorMessage = null;
        $partialEffects = [];
        $unexecuted = [];

        foreach ($this->plan->operations as $index => $operation) {
            try {
                $this->executor->execute($operation);
                $completed[] = $operation->description();
                $counts = $counts->increment($operation->action);
            } catch (Throwable $e) {
                $failedOperation = $operation->description();
                $errorMessage = $e->getMessage();
                $partialEffects = $e instanceof OperationExecutionException ? $e->effects : [];
                foreach (array_slice($this->plan->operations, $index + 1) as $pending) {
                    $unexecuted[] = $pending->description();
                }
                break;
            }
        }

        return new SynchronizerResult(
            $counts,
            count($this->plan->operations),
            count($completed),
            $completed,
            $failedOperation,
            $errorMessage,
            $partialEffects,
            $unexecuted
        );
    }
}
