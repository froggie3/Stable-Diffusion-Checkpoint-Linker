<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

final class Counter
{
    private array $counts = [];

    public function increment(string $key): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }

    public function get(string $key): int
    {
        return $this->counts[$key] ?? 0;
    }
}