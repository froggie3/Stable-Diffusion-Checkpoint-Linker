<?php

declare(strict_types=1);

namespace classes;

/*
 * コマンドライン引数をパース
 */

class Option
{
    // --unlink オプションがあるか？
    public function is_unlink(array $options): bool
    {
        return (isset($options['unlink']))
            ? true
            : false;
    }

    // --json オプションがあるか？
    public function has_json(array $options): bool
    {
        return (isset($options['json']))
            ? true
            : false;
    }

    // --symlink オプションがあるか？
    public function has_symlink(array $options): bool
    {
        return (isset($options['symlink']))
            ? true
            : false;
    }
}
