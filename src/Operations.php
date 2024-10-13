<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Iigau\StableDiffusionCheckpointLinker\Procedures;

final class Operations
{
    private static $keysLookup = array(
        'checkpoint', 'vae', 'embeddings', 'hypernetworks', 'lora', 'controlnet'
    );

    private array $source;
    private array $target;
    private Procedures $procedures;

    function __construct(array $configParameters)
    {
        $this->source = $configParameters['source'];
        $this->target = $configParameters['destination'];
        $this->procedures = new Procedures();
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
        foreach (self::$keysLookup as $currentKey) {
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
                $this->procedures->unlink,
                fn (string $weight): string => $this->target[$currentKey] . "/$weight"
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
                    $this->procedures->link,
                    fn (string $weight): array => array(
                        "$baseDirectory/$weight",
                        $this->target[$currentKey] . "/$weight",
                    )
                );
                $this->retrieveItemsThenPush(
                    $ignoreList,
                    $this->procedures->unlink,
                    fn (string $weight): string => $this->target[$currentKey] . "/$weight"
                );
            } else {
                $this->retrieveItemsThenPush(
                    $weightsList,
                    $this->procedures->unlink,
                    fn (string $weight): string => $this->target[$currentKey] . "/$weight"
                );
            }
        }

        return $this->procedures;
    }
}
