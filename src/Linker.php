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

    public function link(string $source, string $target): LinkResult 
    {
        // sourceが見つからない (symlink()ではカバーできない)
        if (!file_exists($source)) {
            return LinkResult::NOT_LINKED;
        }

        // symlink()は宛先パスが作成時点で空のときのみtrueを返す
        $result = @symlink($source, $target);
        if (!$result) {
            // targetに既にファイルが存在する場合
            return LinkResult::NOT_LINKED;
        }

        // すでにリンクが張られていることを検知して、差分だけを表示する
        $filename = basename($target);
        $lockfile = __DIR__ . "/../.cache/$filename.lock";

        // シンボリックリンクを貼った過去の記録があればスルー、なければ作成
        if (!file_exists($lockfile)) {
            touch($lockfile);
            return LinkResult::NEWLY_LINKED;
        }

        return LinkResult::ALREADY_LINKED;
    }
}
