<?php

declare(strict_types=1);

namespace classes;

/*
 * メッセージなど
 */

class Message
{
    const UNLINKED_OK = 'リンクを削除しました';
    const LINKED_OK = 'リンクを作成しました';
    const JSON_BAD_CONTENT = '不正な設定ファイルです';
    const JSON_BAD_PATH = '指定されたJSONファイルのパス名が不正です';
}
