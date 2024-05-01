<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

final class Linker
{
    public Logger $logger;

    function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function link(string $source, string $target): bool
    {
        $result = @symlink($source, $target);

        // すでにリンクが張られていることを検知して、差分だけを表示する
        $filename = basename($target);
        $lockfile = __DIR__ . "/../.cache/$filename.lock";

        // symlinkは宛先パスが作成時点で空でなければfalseを返す
        if ($result) {
            // シンボリックリンクを貼った過去の記録があればスルー、なければ作成
            if (!file_exists($lockfile)) {
                touch($lockfile);

                $this->logger->debug("successfully made symbolic link", [
                    'source' => $source,
                    'target' => $target,
                ]);
                return true;
            }
        }

        return false;
    }
}
