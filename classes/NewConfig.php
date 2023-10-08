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
     * @param array contains the $argv value 
     */
    public function __construct(string $name, array $args)
    {
        $this->program = $name;
        $this->args = $args;
    }

    public function run(): void
    {
        $json_path = $this->newconfig();

        if ($this->confirm($json_path)) {
            $state = $this->generate_template($json_path);
            $this->post_func($state);
        }
    }

    private function newconfig(): string
    {
        $message = array(
            'usage' => "Usage: $this->program <foo.json>",
            'error' =>
            "The command '$this->program' takes an another argument for configuration path!",
        );

        $value = array_search($this->program, $this->args);
        $json_path = $this->args[$value + 1] ?? null;

        if ($json_path === null) {
            echo $message['usage'] . PHP_EOL;
            echo $message['error'] . PHP_EOL;
            exit(1);
        } else {
            return $json_path;
        }
    }

    /**
     * Confirming when the command is called
     * 
     * @param string $json_name the filename for JSON
     * @return bool whether execution for the operation is confirmed 
     */
    private function confirm(string $json_name): bool
    {
        $message = array(
            'confirm_override' =>
            "$json_name seems to already exists. Are you sure to override the file? [y/N]",
            'confirm_override_2' =>
            "Are you *really* sure to override the current $json_name? [y/N]",
            'confirm' =>
            "Are you sure to make a new configuration to $json_name? [y/N]",
        );

        if (file_exists($json_name)) {
            echo $message['confirm_override'], ': ';
            $input = strtolower(trim(fgets(STDIN) ?: ''));

            if ($input === 'y') {
                echo $message['confirm_override_2'], ': ';
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
    private function generate_template(string $filename): array
    {
        $template_data = $this->json_template();

        if (!$fp = fopen($filename, 'w')) {;
            echo PHP_EOL . "Cannot open file $filename";
            exit(1);
        }

        if (!fwrite($fp, $template_data)) {
            echo PHP_EOL . "Cannot write to file $filename";
            exit(1);
        }

        return [fclose($fp), $filename];
    }

    /**
     * Showing the status after generation for config finishes 
     * 
     * @return array $array status given from generate_template() 
     */
    private function post_func(array $array): void
    {
        list($state, $filename) = $array;

        if ($state && file_exists($filename)) {
            echo 'Yay! wrote a template to: ', realpath($filename), '!';
        }
    }

    private function json_template(): string
    {
        $template = array(
            'destination' => array(
                'webui' => 'C:/foo/stable-diffusion-webui',
                'checkpoint' => 'C:/foo/stable-diffusion-webui/models/Stable-diffusion',
                'vae' => 'C:/foo/stable-diffusion-webui/models/VAE',
                'embeddings' => 'C:/foo/stable-diffusion-webui/embeddings',
                'hypernetworks' => 'C:/foo/stable-diffusion-webui/models/hypernetworks',
                'lora' => 'C:/foo/stable-diffusion-webui/models/Lora',
            ),
            'source' => array(
                'checkpoint' => array(array(
                    'meta' => array(
                        'comment' => '',
                        'enabled' => false,
                    ),
                    'baseDirectory' => '',
                    'weightsList' => array(''),
                    'ignoreList' => array(''),
                )),
                'vae' => array(array(
                    'meta' => array(
                        'comment' => '',
                        'enabled' => false,
                    ),
                    'baseDirectory' => '',
                    'weightsList' => array(''),
                    'ignoreList' => array(''),
                )),
                'embeddings' => array(array(
                    'meta' => array(
                        'comment' => '',
                        'enabled' => false,
                    ),
                    'baseDirectory' => '',
                    'weightsList' => array(''),
                    'ignoreList' => array(''),
                )),
                'hypernetworks' => array(array(
                    'meta' => array(
                        'comment' => '',
                        'enabled' => false,
                    ),
                    'baseDirectory' => '',
                    'weightsList' => array(''),
                    'ignoreList' => array(''),
                )),
                'lora' => array(array(
                    'meta' => array(
                        'comment' => '',
                        'enabled' => false,
                    ),
                    'baseDirectory' => '',
                    'weightsList' => array(''),
                    'ignoreList' => array(''),
                ),),
            ),
        );

        $json_encoded = json_encode(
            $template,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        return $json_encoded;
    }
}
