<?php

declare(strict_types=1);

namespace classes;

/*
 * 操作の実行にかかわるやつ
 */

class Execute
{
    public $filename;


    public function execute(
        string $TMP_SOUR,
        string $TMP_DEST,
        int $is_unlink,
        bool $has_symlink = false
    ) {
        // オプションに応じて違うメソッドを動かす

        if ($is_unlink === 0) {
            $this->mklink($TMP_SOUR, $TMP_DEST, $has_symlink);
        } else {
            $this->unlink($TMP_DEST);
        }
    }

    public function mklink(
        string $TMP_SOUR,
        string $TMP_DEST,
        bool $has_symlink
    ) {
        for ($i = 0; $i <= 1; $i++) {
            if ($i <= 0 && !file_exists($TMP_DEST)) {
                if (!$has_symlink) {
                    link($TMP_SOUR, $TMP_DEST);
                } else {
                    symlink($TMP_SOUR, $TMP_DEST);
                }
                echo $TMP_SOUR, ' <===> ', $TMP_DEST, "\n";
            } else {
                // return error and do nothing for files available
                #printf("%s", $message[3]);
                break;
            }
            if ($i >= 1 && file_exists($TMP_DEST)) {
                #printf("%s", $message['UNLINKED']);
                break; // 正常終了
            } else {
                break; // 異常終了
            }
        }
    }

    public function unlink(string $TMP_DEST)
    {
        for ($i = 0; $i <= 1; $i++) {
            if ($i <= 0 && file_exists($TMP_DEST)) {
                unlink($TMP_DEST);
                echo 'x=> ', $TMP_DEST, "\n";
            } else {
                // return error and do nothing for files unavailable
                #printf("%s", $message['']);
                break;
            }
            if ($i >= 1 && !file_exists($TMP_DEST)) {
                #printf("%s", $mesage['LINKED']);
                break; // 正常終了
            } else {
                break; // 異常終了
            }
        }
    }
}
