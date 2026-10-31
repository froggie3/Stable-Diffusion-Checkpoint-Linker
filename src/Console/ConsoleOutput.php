<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

final class ConsoleOutput
{
    public function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}
