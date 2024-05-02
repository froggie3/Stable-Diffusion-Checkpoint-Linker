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

    function __construct(array $configParameters, Procedures $procedures)
    {
        $this->source = $configParameters['source'];
        $this->target = $configParameters['destination'];
        $this->procedures = $procedures;
    }

    public function retrieveItemsThenPush(array $search, ProceduresArray &$arr, callable $callback): void
    {
        foreach ($search as $weight) {
            if (empty($weight)) continue;
            $arr[] = $callback($weight);
        }
    }

    public function clear(): Procedures
    {
        $keysLookup = self::$keysLookup;

        foreach ($keysLookup as $current_key) {
            foreach ($this->source[$current_key] as
                list(
                    'weightsList'   => $weightsList,
                    'meta'          => list('enabled' => $isEnabledWeight),
                    'baseDirectory' => $baseDirectory
                )) {

                // ignorelist can be omitted, meaning values are not always
                // accessible with a specific key 
                $ignoreList = $weights['ignoreList'] ?? [];
                $weightsList = $weightsList ?? [];

                $this->retrieveItemsThenPush(
                    [...$weightsList, ...$ignoreList],
                    $this->procedures->unlink,
                    fn (string $weight): string => $this->target[$current_key] . "/$weight"
                );
            }
        }

        return $this->procedures;
    }

    public function sync(): Procedures
    {
        $keysLookup = self::$keysLookup;

        foreach ($keysLookup as $current_key) {
            foreach ($this->source[$current_key] as
                list(
                    'weightsList'   => $weightsList,
                    'meta'          => list('enabled' => $isEnabledWeight),
                    'baseDirectory' => $baseDirectory
                )) {

                // ignorelist can be omitted, meaning values are not always
                // accessible with a specific key 
                $ignoreList = $weights['ignoreList'] ?? array();

                if ($isEnabledWeight) {
                    $this->retrieveItemsThenPush(
                        $weightsList,
                        $this->procedures->link,
                        fn (string $weight): array => array(
                            "$baseDirectory/$weight",
                            $this->target[$current_key] . "/$weight",
                        )
                    );
                    $this->retrieveItemsThenPush(
                        $ignoreList,
                        $this->procedures->unlink,
                        fn (string $weight): string =>$this->target[$current_key] . "/$weight"
                    );
                } else {
                    $this->retrieveItemsThenPush(
                        $weightsList,
                        $this->procedures->unlink,
                        fn (string $weight): string => $this->target[$current_key] . "/$weight"
                    );
                }
            }
        }

        return $this->procedures;
    }
}
