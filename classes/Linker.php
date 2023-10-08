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

    private static $keyList = array(
        'checkpoint',
        'vae',
        'embeddings',
        'hypernetworks',
        'lora',
        'controlnet'
    );
    private array $options;
    private array $jsonParams;

    /**
     * Run the linking/unlinking process.
     */
    public function run(): int {
        $this->options = getopt('', array('symlink', 'json:'));
        $this->jsonParams = $this->configVariablesImport();

        try {
            if (!$this->jsonParams) {
                throw new Exception("failed to import config file");
            }
        } catch (Exception $e){
            return 1;
        }

        $operationList = $this->sourceWalk();
        $symlink = isset($this->options[Linker::OPERATION_SYMLINK]);

        $this->processLinks($operationList['link'], $symlink);
        $this->processUnlinks($operationList['unlink']);

        $this->displaySummary(count($operationList['link']), count($operationList['unlink']));

        return 0;
    }

    private function processLinks(array $links, bool $symlink = false): void {
        foreach ($links as $pathPair) {
            [$src, $dest] = [$pathPair['src'], $pathPair['dest']];

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

    private function filterSourcesAvailable($source, array $keyList): array {
        // check the existence of each key before adding them to the list
        $filtered = [];
        foreach ($keyList as $v) {
            // skip keys not existed
            if (!isset($source[$v])) {
                echo "the config file does not include $v section\n";
                continue;
            }
            $filtered[] = $v;
        }
        return $filtered;
    }

    private function sourceWalk(): array {
        $source = $this->jsonParams[self::JSON_SOURCE_KEY];
        $operationList = array('link' => array(), 'unlink' => array());

        // check the existence of each key before adding them to the list
        $keyList = $this->filterSourcesAvailable($source, self::$keyList);

        foreach ($keyList as $currentKey) {

            foreach ($source[$currentKey] as
                list(
                    'weightsList'   => $weightsList,
                    'meta'          => list('enabled' => $weightsEnabled),
                    'baseDirectory' => $baseDirectory
                )) {

                // ignoreList can be omitted, meaning values are not always
                // accessible with a specific key
                $ignoreList = $weights['ignoreList'] ?? array();

                if ($weightsEnabled && !empty($weightsList)) {
                    foreach ($weightsList as $weight) {
                        if (empty($weight)) continue;
                        $operationList['link'][] = array(
                            'src' => joinPaths($baseDirectory, $weight),
                            'dest' => joinPaths(
                                $this->whichDest($currentKey),
                                $weight
                            ),
                        );
                    }

                    if (empty($ignoreList)) continue;

                    foreach ($ignoreList as $weight) {
                        if (empty($weight)) continue;
                        $operationList['unlink'][] =
                            joinPaths($this->whichDest($currentKey), $weight);
                    }
                } else {
                    foreach ($weightsList as $weight) {
                        if (empty($weight)) continue;
                        $operationList['unlink'][] =
                            joinPaths($this->whichDest($currentKey), $weight);
                    }
                }
            }
        }
        return $operationList;
    }

    /**
     * Returns the proper destination set in the settings
     *
     * @param string
     * @return string
     */
    private function whichDest(string $keyName): string {
        $destList = $this->jsonParams[self::JSON_DESTINATION_KEY];

        # just find a proper key-value (specific path) pairs
        foreach ($destList as $currentKey => $currentDest) {
            if ($keyName === $currentKey)
                return $currentDest;
        }
    }

    /**
     * Returns actual value when the value of argument exists
     * otherwise returns false
     */
    private function resolveArgumentValue(string $argument, array $argumentList): string {
        try {
            if (array_key_exists($argument, $argumentList)) {
                return $argumentList[$argument];
            }
            throw new Exception("Lack of mandatory arguments: $argument");
        } catch (Exception $e) {
            // echo "Usage: add --json PATH to specify a config file\n";
            echo $e->getMessage() . PHP_EOL;
            return "";
        }
    }

    /**
     * Imports parameters from JSON
     *
     * @return array the associated array converted or parsed from JSON
     */
    private function configVariablesImport(): array {

        // command-line arguments include --json
        $jsonPath = $this->resolveArgumentValue('json', $this->options);
        $params = [];

        // try reading the path from stdin.
        if (!$jsonPath) {
            echo "input your path to config file (Ctrl-C or EOF to abort): ";
            if (is_string($line = fgets(STDIN))) {
                $jsonPath = trim($line);
            } else { // bool
                return [];
            }
        }

        // Check if .json is available but otherwise exit
        try {
            if (!($jsonPath && file_exists($jsonPath))) {
                throw new Exception(Linker::ERROR_INVALID_JSON_PATH);
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            return [];
        }

        // Check if .json is valid but otherwise exit
        try {
            if (!$params = json_decode(file_get_contents($jsonPath), true)) {
                throw new Exception(Linker::ERROR_INVALID_JSON);
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            return [];
        }

        return $params;
    }
}