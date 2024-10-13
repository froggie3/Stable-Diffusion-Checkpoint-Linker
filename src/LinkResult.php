<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

enum LinkResult
{
    case SOURCE_NOT_FOUND;
    case NOT_LINKED; // ロックファイル、シンボリックリンクの存在のつじつまが合わない
    case NEWLY_LINKED;
    case ALREADY_LINKED;
    case SYMLINK_ERROR;
    case TOUCH_ERROR;
}
