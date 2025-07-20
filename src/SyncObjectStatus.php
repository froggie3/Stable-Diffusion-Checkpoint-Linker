<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

enum SyncObjectStatus: string
{
    case SOURCE_NOT_FOUND = 'source_not_found';
    case TARGET_ALREADY_EXISTS_WITHOUT_RECORD = 'target_exists_without_record'; // 実リンクはあるが記録なし
    case RECORD_EXISTS_WITHOUT_LINK = 'record_exists_without_link'; // ロックレコードがあるのにリンクなし
    case ALREADY_LINKED = 'already_linked';
    case READY = 'ready';
}
