<?php

declare(strict_types=1);

namespace classes;

/*
 * コマンドライン引数をパース
 */

class Option
{
    // --unlink オプションがあるか？
    public function is_unlink(array $options): int
    {
        return (isset($options['unlink']))
            ? 1
            : 0;
    }

    // --json オプションがあるか？
    public function has_json(array $options): bool
    {
        return (isset($options['json']))
            ? true
            : false;
    }
}
