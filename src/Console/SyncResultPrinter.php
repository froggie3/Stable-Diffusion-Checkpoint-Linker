<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

use Iigau\StableDiffusionCheckpointLinker\Sync\SynchronizerResult;
use Psr\Log\LoggerInterface;

final class SyncResultPrinter
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function print(SynchronizerResult $result, bool $verbose): void
    {
        $counts = $result->counts;
        $summary = [
            'created links' => $counts->created,
            'repaired links' => $counts->repaired,
            'replaced links' => $counts->replaced,
            'registered links' => $counts->registered,
            'removed links or records' => $counts->removed,
            'unchanged targets' => $counts->unchanged,
            'operations completed' => "{$result->completedCount}/{$result->operationCount}",
        ];

        if ($result->isSuccessful()) {
            if ($verbose) {
                foreach ($result->completedOperations as $operation) {
                    $this->logger->info('completed: {operation}', ['operation' => $operation]);
                }
                foreach ($summary as $label => $value) {
                    $this->logger->info('{label}: {value}', ['label' => $label, 'value' => $value]);
                }
            }
            return;
        }

        foreach ($result->completedOperations as $operation) {
            $this->logger->error('completed: {operation}', ['operation' => $operation]);
        }
        $this->logger->error('failed: {operation}: {message}', [
            'operation' => $result->failedOperation,
            'message' => $result->errorMessage,
        ]);
        foreach ($result->partialEffects as $effect) {
            $this->logger->error('partial effect before failure: {effect}', ['effect' => $effect]);
        }
        foreach ($result->unexecutedOperations as $operation) {
            $this->logger->error('not executed: {operation}', ['operation' => $operation]);
        }
        $this->logger->error('operations completed: {completed}/{total}', [
            'completed' => $result->completedCount,
            'total' => $result->operationCount,
        ]);
        $this->logger->error('completed work is retained; inspect the filesystem and database before retrying.');
    }
}
