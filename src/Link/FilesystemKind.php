<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Link;

enum FilesystemKind: string
{
    case ABSENT = 'absent';
    case FILE = 'file';
    case DIRECTORY = 'directory';
    case OTHER = 'other';
    case SYMLINK = 'symlink';
}
