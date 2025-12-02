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

        foreach ($this->procedures->operations as $operation) {
            $operation->execute();
        }

        return new SyncronizerResult(
            $counter->get('newlyLinked'),
            $counter->get('error'),
            $counter->get('removed'),
            $counter->get('alreadyLinked'),
            count($this->procedures->operations),
            $counter->get('inconsistent')
        );
    }
}
