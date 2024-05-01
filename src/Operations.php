<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

class Operations
{
    private static $key_list = array(
        'checkpoint', 'vae', 'embeddings', 'hypernetworks', 'lora', 'controlnet'
    );

    private array $json_params;

    function __construct(array $json_params)
    {
        $this->json_params = $json_params;
    }

    public function clear(): array
    {
        $source = $this->json_params['source'];
        $key_list = self::$key_list;
        $operation_list = array("link" => array(), "unlink" => array());

        foreach ($key_list as $current_key) {
            $category = $source[$current_key];

            foreach ($category as
                list(
                    'weightsList'   => $weights_List,
                    'meta'          => list('enabled' => $weights_enabled),
                    'baseDirectory' => $base_directory
                )) {

                // ignorelist can be omitted, meaning values are not always
                // accessible with a specific key 
                $ignore_List = $weights['ignoreList'] ?? array();

                if ($weights_enabled && !empty($weights_List)) {
                    foreach ($weights_List as $weight) {
                        if (empty($weight)) continue;
                        $operation_list['unlink'][] = $this->which_dest($current_key) . "/$weight";
                    }

                    if (empty($ignore_List)) {
                        continue;
                    }

                    foreach ($ignore_List as $weight) {
                        if (empty($weight)) {
                            continue;
                        }
                        $operation_list['unlink'][] = $this->which_dest($current_key) . "/$weight";
                    }
                } else {
                    foreach ($weights_List as $weight) {
                        if (empty($weight)) {
                            continue;
                        }
                        $operation_list['unlink'][] = $this->which_dest($current_key) . "/$weight";
                    }
                }
            }
        }
        return $operation_list;
    }

    public function sync(): array
    {
        $source = $this->json_params['source'];
        $key_list = self::$key_list;
        $operation_list = array("link" => array(), "unlink" => array());

        foreach ($key_list as $current_key) {
            $category = $source[$current_key];

            foreach ($category as
                list(
                    'weightsList'   => $weights_List,
                    'meta'          => list('enabled' => $weights_enabled),
                    'baseDirectory' => $base_directory
                )) {

                // ignorelist can be omitted, meaning values are not always
                // accessible with a specific key 
                $ignore_List = $weights['ignoreList'] ?? array();

                if ($weights_enabled && !empty($weights_List)) {
                    foreach ($weights_List as $weight) {
                        if (empty($weight)) continue;
                        $operation_list['link'][] = array(
                            "src" => "$base_directory/$weight",
                            "dest" => $this->which_dest($current_key) . "/$weight",
                        );
                    }

                    if (empty($ignore_List)) {
                        continue;
                    }

                    foreach ($ignore_List as $weight) {
                        if (empty($weight)) {
                            continue;
                        }
                        $operation_list['unlink'][] = $this->which_dest($current_key) . "/$weight";
                    }
                } else {
                    foreach ($weights_List as $weight) {
                        if (empty($weight)) {
                            continue;
                        }
                        $operation_list['unlink'][] = $this->which_dest($current_key) . "/$weight";
                    }
                }
            }
        }
        return $operation_list;
    }

    /**
     * Returns the proper destination set in the settings 
     * 
     * @param string 
     * @return string 
     */
    private function which_dest(string $key_name): string
    {
        $target_list = $this->json_params['destination'];

        # just find a proper key-value (specific path) pairs
        foreach ($target_list as $current_key => $current_dest) {
            if ($key_name === $current_key) {
                return $current_dest;
            }
        }
    }
}
