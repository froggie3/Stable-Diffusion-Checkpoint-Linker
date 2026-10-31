<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

final class OperationCounts
{
    public function __construct(
        public readonly int $created = 0,
        public readonly int $repaired = 0,
        public readonly int $replaced = 0,
        public readonly int $registered = 0,
        public readonly int $removed = 0,
        public readonly int $unchanged = 0
    ) {
    }

    public function increment(OperationAction $action): self
    {
        return match ($action) {
            OperationAction::CREATE => new self($this->created + 1, $this->repaired, $this->replaced, $this->registered, $this->removed, $this->unchanged),
            OperationAction::REPAIR => new self($this->created, $this->repaired + 1, $this->replaced, $this->registered, $this->removed, $this->unchanged),
            OperationAction::REPLACE => new self($this->created, $this->repaired, $this->replaced + 1, $this->registered, $this->removed, $this->unchanged),
            OperationAction::REGISTER => new self($this->created, $this->repaired, $this->replaced, $this->registered + 1, $this->removed, $this->unchanged),
            OperationAction::UNLINK, OperationAction::DELETE_RECORD => new self($this->created, $this->repaired, $this->replaced, $this->registered, $this->removed + 1, $this->unchanged),
            OperationAction::NOOP => new self($this->created, $this->repaired, $this->replaced, $this->registered, $this->removed, $this->unchanged + 1),
        };
    }
}
