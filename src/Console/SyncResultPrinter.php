<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

use Iigau\StableDiffusionCheckpointLinker\Sync\SynchronizerResult;

final class SyncResultPrinter
{
    public function print(SynchronizerResult $result): void
    {
        foreach ([
            'Created links' => 'created',
            'Repaired links' => 'repaired',
            'Replaced links' => 'replaced',
            'Registered links' => 'registered',
            'Removed links or records' => 'removed',
            'Unchanged targets' => 'unchanged',
        ] as $label => $key) {
            printf("%s: %d\n", $label, $result->counts[$key]);
        }
        printf("Operations completed: %d/%d\n", $result->completedCount, $result->operationCount);
        foreach ($result->completedOperations as $operation) {
            fwrite(STDOUT, "Completed: $operation\n");
        }
        if ($result->failedOperation !== null) {
            fwrite(STDERR, "Failed: {$result->failedOperation}: {$result->errorMessage}\n");
            foreach ($result->partialEffects as $effect) {
                fwrite(STDERR, "  Effect before failure: $effect\n");
            }
            foreach ($result->unexecutedOperations as $operation) {
                fwrite(STDERR, "Not executed: $operation\n");
            }
        }
    }
}
