<?php

declare(strict_types=1);

final class Linker
{
    private static $key_list = array(
        'checkpoint', 'vae', 'embeddings', 'hypernetworks', 'lora'
    );
    private array $options;
    private array $json_params;

    public function run(): void
    {
        $this->options = getopt('', array('symlink', 'json:'));
        $this->json_params = $this->config_variables_import($this->options['json']);

        list('link' => $link, 'unlink' => $unlink) = $this->source_walk();

        // 最終的には "weightsList" において "enabled": false に設定されたモデルは
        // "weightsList" からは削除され、 "ignoreList" に移動されていなければいけない
        // -> 連想配列を作る -> JSON出力
        //$this->addIgnoreListFrom($unlink);

        foreach ($link as list('src' => $src, 'dest' => $dest)) {
            $this->link_by_type($src, $dest);
        }

        foreach ($unlink as $path) {
            $this->weight_unlink($path);
        }

        echo sprintf('Linked %d weights (in disabled: %d weights)', count($link), count($unlink)), PHP_EOL;
    }

    private function source_walk(): array
    {
        $source = $this->json_params['source'];
        $operation_list = array(
            'link' => array(),
            'unlink' => array()
        );

        // Returns a full path to the directory name for the type of models, where the destination can be set in the config.
        $destination_find_from = fn (string $x): string => $this->json_params['destination'][$x];

        foreach (self::$key_list as $current_key) {
            $category = $source[$current_key];

            foreach ($category as
                list(
                    'weightsList'   => $weights_list,
                    'meta'          => list('enabled' => $weights_enabled),
                    'baseDirectory' => $base_directory
                )) {

                $weights_list_keys = array_keys($weights_list);

                //$ignore_list = $weights['ignoreList'] ?? array();

                if ($weights_enabled && !empty($weights_list_keys)) {
                    foreach ($weights_list_keys as $weight) {
                        if (!empty($weight)) {
                            $operation_list['link'][] = array(
                                'src' => join_paths($base_directory, $weight),
                                'dest' => join_paths($destination_find_from($current_key), $weight),
                            );
                        }
                    }
                } else {
                    foreach ($weights_list_keys as $weight) {
                        if (!empty($weight)) {
                            $operation_list['unlink'][] = join_paths($destination_find_from($current_key), $weight);
                        }
                    }
                }
            }
        }
        return $operation_list;
    }

    /**
     * Imports parameters from JSON
     * @param string $json_path full path to configuration
     * @return array the associated array converted or parsed from JSON
     */
    private function config_variables_import(string $json_path): array
    {
        // Check if .json is available but otherwise exit
        try {
            if ($json_path) {
                if (file_exists($json_path)) {
                    $params = json_decode(file_get_contents($json_path), true) ?? false;
                } else {
                    throw new Exception('Invalid path for JSON');
                }
            } else {
                throw new Exception('Usage: add \"--json PATH\" to specify a config file');
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

    /**
     * Unlink に追加されたモデルファイルから $ignoreList を生成
     */
    private function addIgnoreListFrom(array $files): array
    {
        $ignoreList = $files;
        return $ignoreList;
    }

    private function link_by_type(string $src, string $dest): void
    {
        if (!file_exists($src)) {
            echo "$src not found", PHP_EOL;
            return;
        } elseif (!file_exists($dest)) {
            echo "$dest not found", PHP_EOL;
            return;
        } else {
            if (isset($this->options['symlink'])) {
                $this->weight_symlink($src, $dest);
            } else {
                $this->weight_hardlink($src, $dest);
            }
        }
    }

    private function weight_hardlink(string $src, string $dest): void
    {
        link($src, $dest);

        if (isset($error['message'])) {
            $error = error_get_last() ?? array();
            if ($error['message'] === 'link(): Improper link') {
                echo $error['message'], ': ';
                echo 'Try adding --symlink option', PHP_EOL;
            }
        }
    }

    private function weight_symlink(string $src, string $dest): void
    {
        symlink($src, $dest);
    }

    private function weight_unlink(string $filename): void
    {
        if (!file_exists($filename)) {
            echo "$filename not found", PHP_EOL;
            return;
        } else {
            unlink($filename);
        }
    }
}
