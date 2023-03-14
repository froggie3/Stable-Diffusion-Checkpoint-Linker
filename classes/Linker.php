<?php

declare(strict_types=1);

class Linker
{
    private const key_list = array(
        'checkpoint', 'vae', 'embeddings', 'hypernetworks', 'lora'
    );
    private $options = array();
    private $json_params = array();

    public function run(): void
    {
        $this->options = getopt('', array('symlink', 'json:'));
        $this->json_params = $this->config_variables_import();

        list('link' => $link, 'unlink' => $unlink) = $this->source_walk();

        foreach ($link as $path_pair) {
            $this->link_by_type($path_pair['src'], $path_pair['dest']);
        }

        foreach ($unlink as $path) {
            $this->weight_unlink($path);
        }

        printf(
            "Linked %s weights (in disabled: %s weights)" . PHP_EOL,
            count($link),
            count($unlink)
        );
    }

    private function source_walk(): array
    {
        $source = $this->json_params['source'];
        $key_list = self::key_list;
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
                            "src" => join_paths($base_directory, $weight),
                            "dest" => join_paths(
                                $this->which_dest($current_key),
                                $weight
                            ),
                        );
                    }

                    if (empty($ignore_List)) continue;

                    foreach ($ignore_List as $weight) {
                        if (empty($weight)) continue;
                        $operation_list['unlink'][] =
                            join_paths($this->which_dest($current_key), $weight);
                    }
                } else {
                    foreach ($weights_List as $weight) {
                        if (empty($weight)) continue;
                        $operation_list['unlink'][] =
                            join_paths($this->which_dest($current_key), $weight);
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
        $dest_list = $this->json_params['destination'];

        # just find a proper key-value (specific path) pairs
        foreach ($dest_list as $current_key => $current_dest) {
            if ($key_name === $current_key)
                return $current_dest;
        }
    }

    /**
     * Imports parameters from JSON
     * 
     * @return array the associated array converted or parsed from JSON
     */
    private function config_variables_import(): array
    {
        $json_path = $this->options['json'] ?? false;

        // Check if .json is available but otherwise exit
        try {
            if ($json_path !== false && file_exists($json_path)) {
                $params = json_decode(file_get_contents($json_path), true) ?? false;
            } elseif ($json_path === false) {
                throw new Exception("Usage: add \"--json PATH\" to specify a config file");
            } else {
                if (!file_exists($json_path))
                    throw new Exception('Invalid path for JSON');
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            exit;
        }

        // Check if .json is valid but otherwise exit
        try {
            if (!$params) {
                throw new Exception('Invalid JSON was detected, check if it is valid.');
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            exit;
        }

        return $params;
    }

    private function link_by_type(string $src, string $dest): void
    {
        if (file_exists($dest)) return;

        if (!file_exists($src)) {
            echo ($src), " not found", PHP_EOL;
            return;
        }

        if (isset($this->options['symlink'])) {
            $this->weight_symlink($src, $dest);
        } else {
            $this->weight_hardlink($src, $dest);
        }
    }

    private function weight_hardlink(string $src, string $dest): void
    {
        link($src, $dest);

        if (isset($error['message'])) {
            $error = error_get_last() ?? array();
            if ($error['message'] === "link(): Improper link") {
                echo $error['message'], ": ";
                echo "Try adding --symlink option \n";
            }
        }
    }

    private function weight_symlink(string $src, string $dest): void
    {
        symlink($src, $dest);
    }

    private function weight_unlink(string $filename): void
    {
        if (!file_exists($filename)) return;

        unlink($filename);
        echo ($filename), " not found", PHP_EOL;
        #echo "unlink $filename";
    }
}
