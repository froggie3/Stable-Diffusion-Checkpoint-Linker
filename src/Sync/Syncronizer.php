<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

use Iigau\StableDiffusionCheckpointLinker\Link\Linker;
use Iigau\StableDiffusionCheckpointLinker\Link\Unlinker;
use Iigau\StableDiffusionCheckpointLinker\Sync\State\SyncStateFactory;

use Monolog\Logger;

final class Syncronizer
{
    public function __construct(
        private Logger $logger,
        private Procedures $procedures,
        private Linker $linker,
        private Unlinker $unlinker,
        private Counter $counter
    ) {}

    public function run(): SyncronizerResult
    {
        $factory = new SyncStateFactory($this->logger, $this->linker, $this->unlinker, $this->counter);

        foreach ($this->procedures->operations as $operation) {
            $operation->execute();
        }

        return new SyncronizerResult(
            $this->counter->get('newlyLinked'),
            $this->counter->get('error'),
            $this->counter->get('removed'),
            $this->counter->get('alreadyLinked'),
            count($this->procedures->operations),
            $this->counter->get('inconsistent')
        );
    }
}
