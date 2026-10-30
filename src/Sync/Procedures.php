<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

use Iigau\StableDiffusionCheckpointLinker\Sync\Operation\Operation;

final class Procedures
{
    /** @var Operation[] */
    public array $operations;

    public function __construct()
    {
        $this->operations = [];
    }
}
