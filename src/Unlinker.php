<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

final class Unlinker
{
    public Logger $logger;

    function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function unlink(string $path): bool
    {
        $result = @unlink($path);

        $filename = basename($path);
        $lockfile = __DIR__ . "/../.cache/$filename.lock";

        // ないものを消そうとしたらfalseを返すので、これらは除く
        if ($result) {
            if (file_exists($lockfile)) {
                unlink($lockfile);

                $this->logger->debug("successfully unlinked", [
                    'path' => $path,
                ]);
                return true;
            }
        }

        return false;
    }
}
