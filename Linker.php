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

        $op_list = $this->source_walk();

        foreach ($op_list['link'] as $path_pair) {
            $this->link_by_type($path_pair['src'], $path_pair['dest']);
        }

        foreach ($op_list['unlink'] as $path) {
            $this->weight_unlink($path);
        }

        printf(
            "Linked %s weights (in disabled: %s weights)" . PHP_EOL,
            count($op_list['link']),
            count($op_list['unlink'])
        );
    }

    private function source_walk(): array
    {
        $json_params = $this->config_variables_import();
        $source = $json_params['source'];
        $key_list = self::key_list;
        $operation_list = array("link" => array(), "unlink" => array());

        foreach ($key_list as $current_key) {
            $elem = $source[$current_key];

            # walking around json
            foreach ($elem as $weights) {
                $weights_List = $weights['weightsList'] ?? [];
                $ignore_List = $weights['ignoreList'] ?? [];
                $weights_enabled = $weights['meta']['enabled'] || false;

                if ($weights_enabled && !empty($weights_List)) {

                    # "weightLists"
                    foreach ($weights_List as $weight) {
                        if (empty($weight)) continue;
                        $base_directory = $weights['baseDirectory'];
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

    # returns a proper destination written in the settings
    private function which_dest(string $key_name): string
    {
        $dest_list = $this->json_params['destination'];

        # just find a proper key-value (specific path) pairs
        foreach ($dest_list as $current_key => $current_dest) {
            if ($key_name === $current_key)
                return $current_dest;
        }
    }
    private function config_variables_import(): array
    {
        if (isset($this->options['json'])) {
            $json_path = $this->options['json'];
        }

        // Check if .json is available but otherwise exit
        try {
            if (isset($json_path) && file_exists($json_path)) {
                $params = json_decode(file_get_contents($json_path), true) ?? false;
            } elseif (!$json_path) {
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