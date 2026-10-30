<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

final class OperationPlan
{
    /** @var list<PlannedOperation> */
    public array $operations = [];
    /** @var list<string> */
    public array $errors = [];

    public function add(PlannedOperation $operation): void
    {
        $this->operations[] = $operation;
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
