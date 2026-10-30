<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

use Throwable;

final class Synchronizer
{
    public function __construct(
        private OperationPlan $plan,
        private OperationExecutor $executor
    ) {}

    public function run(): SynchronizerResult
    {
        $counts = ['created' => 0, 'repaired' => 0, 'replaced' => 0, 'registered' => 0, 'removed' => 0, 'unchanged' => 0];
        $completed = [];
        $failedOperation = null;
        $errorMessage = null;
        $partialEffects = [];
        $unexecuted = [];

        foreach ($this->plan->operations as $index => $operation) {
            try {
                $this->executor->execute($operation);
                $completed[] = $operation->description();
                $key = match ($operation->action) {
                    'create' => 'created',
                    'repair' => 'repaired',
                    'replace' => 'replaced',
                    'register' => 'registered',
                    'unlink', 'delete_record' => 'removed',
                    default => 'unchanged',
                };
                ++$counts[$key];
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
