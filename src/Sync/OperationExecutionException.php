<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

use RuntimeException;

final class OperationExecutionException extends RuntimeException
{
    public function __construct(string $message, public readonly array $effects = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
