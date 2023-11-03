<?php

declare(strict_types=1);

final class NewConfig
{
    private string $program;
    private array $args;

    /**
     * The constructor 
     * 
     * @param string $name the name for subcommand 
     * @param array $args contains the $argv value 
     */
    public function __construct(string $name, array $args)
    {
        $this->program = $name;
        $this->args = $args;
    }

    public function run(): void
    {
        $jsonPath = $this->newConfig();

        if ($this->confirm($jsonPath)) {
            $state = $this->generateTemplate($jsonPath);
            $this->postFunc($state);
        }
    }

    private function newConfig(): string
    {
        $message = [
            'usage' => "Usage: $this->program <foo.json>",
            'error' => "The command '$this->program' takes another argument for configuration path!",
        ];

        $value = array_search($this->program, $this->args);
        $jsonPath = $this->args[$value + 1] ?? null;

        if ($jsonPath === null) {
            echo $message['usage'] . PHP_EOL;
            echo $message['error'] . PHP_EOL;
            exit(1);
        } else {
            return $jsonPath;
        }
    }

    /**
     * Confirming when the command is called
     * 
     * @param string $jsonName the filename for JSON
     * @return bool whether execution for the operation is confirmed 
     */
    private function confirm(string $jsonName): bool
    {
        $message = [
            'confirmOverride' =>
                "$jsonName seems to already exists. Are you sure to override the file? [y/N]",
            'confirmOverride2' =>
                "Are you *really* sure to override the current $jsonName? [y/N]",
            'confirm' =>
                "Are you sure to make a new configuration to $jsonName? [y/N]",
        ];

        if (file_exists($jsonName)) {
            echo $message['confirmOverride'], ': ';
            $input = strtolower(trim(fgets(STDIN) ?: ''));

            if ($input === 'y') {
                echo $message['confirmOverride2'], ': ';
                $input = strtolower(trim(fgets(STDIN) ?: ''));

                if ($input === 'y') {
                    return true;
                }
            }
        } else {
            echo $message['confirm'], ': ';
            $input = strtolower(trim(fgets(STDIN) ?: ''));

            if ($input === 'y') {
                return true;
            }
        }

        return false;
    }

    /**
     * Generating template and writing into a file 
     * 
     * @param string $filename the filename for JSON
     * @return array the status for 'fclose()', and the filename for JSON 
     */
    private function generateTemplate(string $filename): array
    {
        $templateData = $this->jsonTemplate();

        if (!$fp = fopen($filename, 'w')) {
            echo PHP_EOL . "Cannot open file $filename";
            exit(1);
        }

        if (!fwrite($fp, $templateData)) {
            echo PHP_EOL . "Cannot write to file $filename";
            exit(1);
        }

        return [fclose($fp), $filename];
    }

    /**
     * Showing the status after generation for config finishes 
     * 
     * @return array $array status given from generateTemplate() 
     */
    private function postFunc(array $array): void
    {
        list($state, $filename) = $array;

        if ($state && file_exists($filename)) {
            echo 'Yay! wrote a template to: ', realpath($filename), '!';
        }
    }

    private function jsonTemplate(): string
    {
        $template = [
            'destination' => [
                'webui' => 'C:/foo/stable-diffusion-webui',
                'checkpoint' => 'C:/foo/stable-diffusion-webui/models/Stable-diffusion',
                'vae' => 'C:/foo/stable-diffusion-webui/models/VAE',
                'embeddings' => 'C:/foo/stable-diffusion-webui/embeddings',
                'hypernetworks' => 'C:/foo/stable-diffusion-webui/models/hypernetworks',
                'lora' => 'C:/foo/stable-diffusion-webui/models/Lora',
            ],
            'source' => [
                'checkpoint' => [
                    [
                        'meta' => [
                            'comment' => '',
                            'enabled' => false,
                        ],
                        'baseDirectory' => '',
                        'weightsList' => [''],
                        'ignoreList' => [''],
                    ],
                ],
                'vae' => [
                    [
                        'meta' => [
                            'comment' => '',
                            'enabled' => false,
                        ],
                        'baseDirectory' => '',
                        'weightsList' => [''],
                        'ignoreList' => [''],
                    ],
                ],
                'embeddings' => [
                    [
                        'meta' => [
                            'comment' => '',
                            'enabled' => false,
                        ],
                        'baseDirectory' => '',
                        'weightsList' => [''],
                        'ignoreList' => [''],
                    ],
                ],
                'hypernetworks' => [
                    [
                        'meta' => [
                            'comment' => '',
                            'enabled' => false,
                        ],
                        'baseDirectory' => '',
                        'weightsList' => [''],
                        'ignoreList' => [''],
                    ],
                ],
                'lora' => [
                    [
                        'meta' => [
                            'comment' => '',
                            'enabled' => false,
                        ],
                        'baseDirectory' => '',
                        'weightsList' => [''],
                        'ignoreList' => [''],
                    ],
                ],
            ],
        ];

        $jsonEncoded = json_encode(
            $template,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        return $jsonEncoded;
    }
}