<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

enum Command: string
{
    case SYNC = 'sync';
    case CLEAR = 'clear';

    public function description(): string
    {
        return match ($this) {
            self::SYNC => 'Create, repair, replace, register, or remove selected links.',
            self::CLEAR => 'Remove only links confirmed as managed and listed in the configuration.',
        };
    }
}
