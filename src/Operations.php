<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Iigau\StableDiffusionCheckpointLinker\Procedures;

final class Operations
{
    private Procedures $procedures;
    private Counter $counter;
    private SyncStateFactory $factory;

    public function __construct(
        private array $lookupKeys,
        private array $source,
        private array $target,
        private Linker $linker,
        private Unlinker $unlinker,
        \Monolog\Logger $logger
    ) {
        $this->procedures = new Procedures();
        $this->counter = new Counter();
        $this->factory = new SyncStateFactory($logger, $linker, $unlinker, $this->counter);
    }

    public function retrieveItemsThenPush(array $search, array &$arr, callable $callback): void
    {
        foreach ($search as $weight) {
            if (empty($weight)) {
                continue;
            }
            $arr[] = $callback($weight);
        }
    }

    private function array_walker()
    {
        foreach ($this->lookupKeys as $currentKey) {
            // var_dump($currentKey);
            if (!array_key_exists($currentKey, $this->source)) {
                continue;
            }
            foreach ($this->source[$currentKey] as $value) {
                if (!array_key_exists('ignoreList', $value)) {
                    // ignorelist is optional, needs to be accessible with a key
                    $value['ignoreList'] = array();
                }
                yield [
                    'currentKey' => $currentKey,
                    ...$value
                ];
            }
        }
    }

    public function clear(): Procedures
    {
        foreach ($this->array_walker() as
            list(
                'currentKey' => $currentKey,
                'ignoreList' => $ignoreList,
                'weightsList' => $weightsList,
            )) {

            $this->retrieveItemsThenPush(
                [...$weightsList ?? array(), ...$ignoreList ?? array()],
                $this->procedures->operations,
                fn (string $weight): Operation => new UnlinkOperation(
                    $this->target[$currentKey] . "/$weight",
                    $this->unlinker,
                    $this->counter
                )
            );
        }
        return $this->procedures;
    }

    public function sync(): Procedures
    {
        foreach ($this->array_walker() as
            list(
                'currentKey' => $currentKey,
                'weightsList'   => $weightsList,
                'ignoreList' => $ignoreList,
                'meta'          => list('enabled' => $isEnabledWeight),
                'baseDirectory' => $baseDirectory
            )) {

            if ($isEnabledWeight) {
                $this->retrieveItemsThenPush(
                    $weightsList,
                    $this->procedures->operations,
                    fn (string $weight): Operation => new LinkOperation(
                        "$baseDirectory/$weight",
                        $this->target[$currentKey] . "/$weight",
                        $this->linker,
                        $this->counter,
                        $this->factory
                    )
                );
                $this->retrieveItemsThenPush(
                    $ignoreList,
                    $this->procedures->operations,
                    fn (string $weight): Operation => new UnlinkOperation(
                        $this->target[$currentKey] . "/$weight",
                        $this->unlinker,
                        $this->counter
                    )
                );
            } else {
                $this->retrieveItemsThenPush(
                    $weightsList,
                    $this->procedures->operations,
                    fn (string $weight): Operation => new UnlinkOperation(
                        $this->target[$currentKey] . "/$weight",
                        $this->unlinker,
                        $this->counter
                    )
                );
            }
        }

        return $this->procedures;
    }
}
