<?php

declare(strict_types=1);

/**
 * Class Linker
 * Handles linking and unlinking files based on a JSON configuration.
 */
final class Linker {
    const JSON_SOURCE_KEY = 'source';
    const JSON_DESTINATION_KEY = 'destination';
    const OPERATION_SYMLINK = 'symlink';
    const OPERATION_LINK = 'link';
    const ERROR_INVALID_JSON_PATH = 'Invalid path for JSON';
    const ERROR_INVALID_JSON = 'Invalid JSON was detected, check if it is valid.';

    private static $key_list = array(
        'checkpoint',
        'vae',
        'embeddings',
        'hypernetworks',
        'lora',
        'controlnet'
    );
    private array $options;
    private array $json_params;

    /**
     * Run the linking/unlinking process.
     */
    public function run(): int {
        $this->options = getopt('', array('symlink', 'json:'));
        $this->json_params = $this->config_variables_import();

        try {
            if (!$this->json_params) {
                throw new Exception("failed to import config file");
            }
        } catch (Exception $e){
            return 1;
        }

        $operation_list = $this->source_walk();
        $symlink = isset($this->options[Linker::OPERATION_SYMLINK]);

        $this->processLinks($operation_list['link'], $symlink);
        $this->processUnlinks($operation_list['unlink']);

        $this->displaySummary(count($operation_list['link']), count($operation_list['unlink']));

        return 0;
    }

    private function processLinks(array $links, bool $symlink = false): void {
        foreach ($links as $path_pair) {
            [$src, $dest] = [$path_pair['src'], $path_pair['dest']];

            // link already exists
            if (file_exists($dest)) continue;

            if ($symlink) {
                $res = symlink($src, $dest);
            } else {
                $res = link($src, $dest);
            }
        }
    }

    private function processUnlinks(array $unlinkPaths): void {
        foreach ($unlinkPaths as $path) {
            // link already exists
            if (!file_exists($path)) continue;

            $res = unlink($path);
        }
    }

    private function displaySummary(int $linkedCount, int $unlinkedCount): void {
        printf(
            'Linked %s weights (in disabled: %s weights)' . PHP_EOL,
            $linkedCount,
            $unlinkedCount
        );
    }

    private function filter_sources_available($source, array $key_list): array {
        // check the existence of each key before adding them to the list
        $filtered = [];
        foreach ($key_list as $v) {
            // skip keys not existed
            if (!isset($source[$v])) {
                echo "the config file does not include $v section\n";
                continue;
            }
            $filtered[] = $v;
        }
        return $filtered;
    }

    private function source_walk(): array {
        $source = $this->json_params[self::JSON_SOURCE_KEY];
        $operation_list = array('link' => array(), 'unlink' => array());

        // check the existence of each key before adding them to the list
        $key_list = $this->filter_sources_available($source, self::$key_list);

        foreach ($key_list as $current_key) {

            foreach ($source[$current_key] as
                list(
                    'weightsList'   => $weights_list,
                    'meta'          => list('enabled' => $weights_enabled),
                    'baseDirectory' => $base_directory
                )) {

                // ignorelist can be omitted, meaning values are not always
                // accessible with a specific key
                $ignore_list = $weights['ignoreList'] ?? array();

                if ($weights_enabled && !empty($weights_list)) {
                    foreach ($weights_list as $weight) {
                        if (empty($weight)) continue;
                        $operation_list['link'][] = array(
                            'src' => join_paths($base_directory, $weight),
                            'dest' => join_paths(
                                $this->which_dest($current_key),
                                $weight
                            ),
                        );
                    }

                    if (empty($ignore_list)) continue;

                    foreach ($ignore_list as $weight) {
                        if (empty($weight)) continue;
                        $operation_list['unlink'][] =
                            join_paths($this->which_dest($current_key), $weight);
                    }
                } else {
                    foreach ($weights_list as $weight) {
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
    private function which_dest(string $key_name): string {
        $dest_list = $this->json_params[self::JSON_DESTINATION_KEY];

        # just find a proper key-value (specific path) pairs
        foreach ($dest_list as $current_key => $current_dest) {
            if ($key_name === $current_key)
                return $current_dest;
        }
    }

    /**
     * Returns actual value when the value of argument exists
     * otherwise returns false
     */
    private function resolveArgumentValue(string $argument, array $argument_list): string {
        try {
            if (array_key_exists($argument, $argument_list)) {
                return $argument_list[$argument];
            }
            throw new Exception("Lack of mandatory arguments: $argument");
        } catch (Exception $e) {
            //echo "Usage: add --json PATH to specify a config file\n";
            echo $e->getMessage() . PHP_EOL;
            return "";
        }
    }

    /**
     * Imports parameters from JSON
     *
     * @return array the associated array converted or parsed from JSON
     */
    private function config_variables_import(): array {

        // command-line arguments includes --json
        $json_path = $this->resolveArgumentValue('json', $this->options);
        $params = [];

        // try reading path from stdin.
        if (!$json_path) {
            echo "input your path to config file (Ctrl-C or EOF to abort): ";
            if (is_string($line = fgets(STDIN))) {
                $json_path = trim($line);
            } else { // bool
                return [];
            }
        }

        // Check if .json is available but otherwise exit
        try {
            if (!($json_path && file_exists($json_path))) {
                throw new Exception(Linker::ERROR_INVALID_JSON_PATH);
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            return [];
        }

        // Check if .json is valid but otherwise exit
        try {
            if (!$params = json_decode(file_get_contents($json_path), true)) {
                throw new Exception(Linker::ERROR_INVALID_JSON);
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            return [];
        }

        return $params;
    }
}
