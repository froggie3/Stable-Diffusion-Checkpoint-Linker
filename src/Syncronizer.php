<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

final class Syncronizer
{
    public Logger $logger;
    public array $operation_list;
    public Linker $linker;
    public Unlinker $unlinker;

    function __construct(Logger $logger, array $operation_list, Linker $linker, Unlinker $unlinker)
    {
        $this->logger = $logger;
        $this->operation_list = $operation_list;
        $this->linker = $linker;
        $this->unlinker = $unlinker;
    }

    public function run(): void
    {
        list('link' => $link, 'unlink' => $unlink) = $this->operation_list;

        $added = 0;
        $removed = 0;

        foreach ($link as list('src' => $source, 'dest' => $target)) {
            $result = $this->linker->link($source, $target);
            if ($result) {
                ++$added;
            }
        }

        foreach ($unlink as $path) {
            $result = $this->unlinker->unlink($path);
            if ($result) {
                ++$removed;
            }
        }

        printf("%15s%10s%10s%15s\n", "newly linked", "removed", "loaded", "in disabled");
        printf("%15d%10d%10d%15d\n", $added, $removed, count($link), count($unlink));
    }
}
