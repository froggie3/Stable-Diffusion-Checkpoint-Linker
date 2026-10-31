<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

enum OperationAction: string
{
    case CREATE = 'create';
    case REPAIR = 'repair';
    case REGISTER = 'register';
    case NOOP = 'noop';
    case REPLACE = 'replace';
    case UNLINK = 'unlink';
    case DELETE_RECORD = 'delete_record';
}
