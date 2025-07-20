<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

enum RecordResult: string
{
    case NEWLY_LINKED = 'newly_linked';
    case RECORD_CREATION_ERROR = 'record_creation_error'; // ロック作成失敗(DB含む)
}
