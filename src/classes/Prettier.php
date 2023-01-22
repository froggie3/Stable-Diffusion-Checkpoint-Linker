<?php

declare(strict_types=1);

namespace classes;

// class to delete unwanted characters in configs
class Prettier
{
    public function doAll($target): string
    {
        if (is_array($target)) {
            // string が出てくるまで再帰する
            foreach ($target as $item) {
                return $this->doAll($item);
            }
        } else {
            return $this->fix_slash($this->removeInnerBackslash($target));
        }
    }

    public function removeInnerBackslash(string $target): string
    {
        return str_replace('\\', '/', $target);
    }

    public function remove_double_dots(string $target): string
    {
        return str_replace('..', '', $target);
    }

    public function fix_slash(string $target): string
    {
        // 行末のスラッシュがない場合は追加
        return trim(trim($target, '/'), '\\') . '/';
    }
}
