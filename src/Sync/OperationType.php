<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

enum OperationType: string
{
    case LINK = 'link';
    case UNLINK = 'unlink';
}
