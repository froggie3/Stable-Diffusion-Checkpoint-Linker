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
        $loadedCount = 0;

        foreach ($this->procedures->link as list($source, $target)) {
            $result = $this->linker->link($source, $target);
            switch ($result) {
                case LinkResult::NEWLY_LINKED:
                    $this->logger->info("successfully linked", ['source' => $source, 'target' => $target,]);
                    ++$newlyLinkedCount;
                    break;
                case LinkResult::ALREADY_LINKED:
                    $this->logger->debug("already linked", ['source' => $source, 'target' => $target,]);
                    ++$loadedCount;
                    break;
                case LinkResult::SOURCE_NOT_FOUND;
                    $this->logger->error("source not found", ['source' => $source]);
                    ++$notLinkedCount;
                    break;
                case LinkResult::SYMLINK_ERROR;
                case LinkResult::TOUCH_ERROR;
                case LinkResult::NOT_LINKED;
                default:
                    $this->logger->error("error occured", ['source' => $source, 'target' => $target,]);
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
            $notLinkedCount,
            $removedCount,
            $loadedCount,
            count($this->procedures->unlink)
        );
    }
}
