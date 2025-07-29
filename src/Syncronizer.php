<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

final class Syncronizer
{
    public function __construct(
        private Logger $logger,
        private Procedures $procedures,
        private Linker $linker,
        private Unlinker $unlinker
    ) {}

    public function run(): SyncronizerResult
    {
        $counter = new Counter();
        $factory = new SyncStateFactory($this->logger, $this->linker, $this->unlinker, $counter);

        foreach ($this->procedures->link as list($source, $target)) {
            $status = $this->linker->test($source, $target);
            $state = $factory->create($status, $source, $target);

            if (!$state->shouldSync()) {
                continue;
            }

            $state->sync();
        }

        foreach ($this->procedures->unlink as $path) {
            if ($this->unlinker->unlink($path)) {
                $counter->increment('removed');
            }
        }

        return new SyncronizerResult(
            $counter->get('newlyLinked'),
            $counter->get('error'),
            $counter->get('removed'),
            $counter->get('alreadyLinked'),
            count($this->procedures->unlink),
            $counter->get('inconsistent')
        );
    }
}
