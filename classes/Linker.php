<?php

declare(strict_types=1);

/**
 * Class Linker
 * Handles linking and unlinking files based on a JSON configuration.
 */
final class Linker
{
    protected const JSON_SOURCE_KEY = 'source';
    protected const JSON_DESTINATION_KEY = 'destination';
    protected const OPERATION_SYMLINK = 'symlink';
    protected const OPERATION_LINK = 'link';

    private array $keyList = array(
        'checkpoint',
        'vae',
        'embeddings',
        'hypernetworks',
        'lora',
        'controlnet'
    );
    private array $options;
    private array $jsonParams;

    public function __construct($params)
    {
        $this->options = getopt('s', array('symlink'));
        $this->jsonParams = $params;
    }

    /**
     * Run the linking/unlinking process.
     */
    public function run()
    {
        $operationList = $this->sourceWalk();
        $symlink = isset($this->options[Linker::OPERATION_SYMLINK]);

        $this->processLinks($operationList['link'], $symlink);
        $this->processUnlinks($operationList['unlink']);

        $this->displaySummary(count($operationList['link']), count($operationList['unlink']));
    }

    private function processLinks(array $links, bool $symlink = false): void
    {
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

    private function processUnlinks(array $unlinkPaths): void
    {
        foreach ($unlinkPaths as $path) {
            // link already exists
            if (!file_exists($path)) continue;

            $res = unlink($path);
        }
    }

    private function displaySummary(int $linkedCount, int $unlinkedCount): void
    {
        printf(
            'Linked %s weights (in disabled: %s weights)' . PHP_EOL,
            $linkedCount,
            $unlinkedCount
        );
    }

    private function filterSourcesAvailable(array $source, array $destination, array $keyList): array
    {
        // check the existence of each key before adding them to the list
        $filtered = [];
        foreach ($keyList as $v) {
            // skip keys not existed
            if (!array_key_exists($v, $source)) {
                echo "the config file does not include '$v' parameter in the 'source' section\n";
                continue;
            }
            if (!array_key_exists($v, $destination)) {
                echo "the config file does not include '$v' parameter in the 'destination' section\n";
                continue;
            }
            $filtered[] = $v;
        }
        return $filtered;
    }

    private function sourceWalk(): array
    {
        $source = $this->jsonParams[self::JSON_SOURCE_KEY];
        $destination = $this->jsonParams[self::JSON_DESTINATION_KEY];
        $operationList = array('link' => array(), 'unlink' => array());

        // check the existence of each key before adding them to the list
        $keyList = $this->filterSourcesAvailable(
            self::$keyList,
            array_keys($source),
            array_keys($destination)
        );

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
                            'src' => Utils::joinPaths($baseDirectory, $weight),
                            'dest' => Utils::joinPaths(
                                $destination[$currentKey],
                                $weight
                            ),
                        );
                    }

                    if (empty($ignoreList)) continue;

                    foreach ($ignoreList as $weight) {
                        if (empty($weight)) continue;
                        $operationList['unlink'][] =
                            Utils::joinPaths($destination[$currentKey], $weight);
                    }
                } else {
                    foreach ($weightsList as $weight) {
                        if (empty($weight)) continue;
                        $operationList['unlink'][] =
                            Utils::joinPaths($destination[$currentKey], $weight);
                    }
                }
            }
        }
        return $operationList;
    }
}
