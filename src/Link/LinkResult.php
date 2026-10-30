<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Link;

enum LinkResult: string
{
    case ACCEPT = 'accept';
    case SYMLINK_ERROR = 'symlink_error';
}
