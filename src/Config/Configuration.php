<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Config;

use InvalidArgumentException;

final class Configuration
{
    /** @param list<string> $lookupKeys @param array<string, CategoryConfiguration> $categories */
    public function __construct(public readonly array $lookupKeys, private readonly array $categories)
    {
    }

    /** @return array<string, CategoryConfiguration> */
    public function categories(): array
    {
        return $this->categories;
    }

    public function category(string $name): CategoryConfiguration
    {
        return $this->categories[$name] ?? throw new InvalidArgumentException("unknown category: $name");
    }
}
