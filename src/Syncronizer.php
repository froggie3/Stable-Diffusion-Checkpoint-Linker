<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

final class Syncronizer
{
    public Logger $logger;
    public Procedures $procedures;
    public Linker $linker;
    public Unlinker $unlinker;

    public function __construct(Logger $logger, Procedures $procedures, Linker $linker, Unlinker $unlinker)
    {
        $this->logger = $logger;
        $this->procedures = $procedures;
        $this->linker = $linker;
        $this->unlinker = $unlinker;
    }

    public function run(): SyncronizerResult
    {
        $newlyLinkedCount = 0;
        $notLinkedCount = 0;
        $removedCount = 0;

        foreach ($this->procedures->link as list($source, $target)) {
            $result = $this->linker->link($source, $target);
            if ($result === LinkResult::NEWLY_LINKED) {
                ++$newlyLinkedCount;
            } elseif ($result === LinkResult::NOT_LINKED) {
                ++$notLinkedCount;
            }
        }

        foreach ($this->procedures->unlink as $path) {
            $result = $this->unlinker->unlink($path);
            if ($result) {
                ++$removedCount;
            }
        }

        return new SyncronizerResult(
            $newlyLinkedCount,
            count($this->procedures->unlink),
            $notLinkedCount,
            $removedCount,
            count($this->procedures->link) - $notLinkedCount
        );
    }
}
