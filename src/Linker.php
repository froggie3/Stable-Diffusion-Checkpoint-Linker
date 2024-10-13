<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

final class Linker
{
    public Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * アルゴリズムについてはこちらを参照
     * https://scrapbox.io/yokkin/20241013
     */
    public function link(string $source, string $target): LinkResult
    {
        // sourceが見つからない (symlink()ではカバーできない)
        if (file_exists($source)) {
            // すでにリンクが張られていることを検知して、差分だけを表示する
            $filename = basename($target);
            $lockfile = __DIR__ . "/../.cache/$filename.lock";

            if (file_exists($target)) {
                if (file_exists($lockfile)) {
                    return LinkResult::ALREADY_LINKED;
                } else {
                    // 論理的におかしい
                    return LinkResult::NOT_LINKED;
                }
            } else {
                // symlink()は宛先パスが作成時点で空のときのみtrueを返す
                $symlinkResult = @symlink($source, $target);
                if ($symlinkResult) {
                    if (file_exists($lockfile)) {
                        // 論理的におかしい
                        return LinkResult::NOT_LINKED;
                    } else {
                        $touchResult = @touch($lockfile);
                        if ($touchResult) {
                            return LinkResult::NEWLY_LINKED;
                        } else {
                            return LinkResult::TOUCH_ERROR;
                        }
                    }
                } else {
                    return LinkResult::SYMLINK_ERROR;
                }
            }
        } else {
            return LinkResult::SOURCE_NOT_FOUND;
        }
    }
}
