<?php

declare(strict_types=1);

namespace classes;

/* 
 * オプションに応じてリンク先のファイルの存在を確認してからリンク操作する
 */

class Execute
{
    public $filename;

    public function execute(string $src, string $dest)
    {
        // オプションを取得し、unlink か link かを決定する
        if (!(new Option)->is_unlink(getopt('', ['unlink']))) {

            if (file_exists($dest) === false) {

                // オプションを取得し、link か symlink かを決定する
                if (!(new Option)->has_symlink(getopt('', ['symlink']))) {
                    link($src, $dest);
                } else {
                    symlink($src, $dest);
                }

                // リンクが適切に貼られたかどうかを確認する
                if (file_exists($dest)) {
                    echo $src, ' <===> ', $dest, "\n";
                }
            }
        } else {
            if (file_exists($dest) || is_link($dest)) {
                unlink($dest);

                // リンクが適切に削除されたかどうかを確認する
                if (!file_exists($dest)) {
                    echo 'x=> ', $dest, "\n";
                }
            }
        }
    }
}
