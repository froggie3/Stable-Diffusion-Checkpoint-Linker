#!/usr/bin/php
<?php

declare(strict_types=1);

class Message
{
    const JSON_BAD_CONTENT =
    'Invalid JSON was detected, check if it is valid.' . PHP_EOL;
    const JSON_BAD_PATH =
    'Invalid path for JSON' . PHP_EOL;
}

function has_newconfig(string $command, array $args): bool
{
    return in_array($command, $args) ?? false;
}

function newconfig($program, array $args): string | null
{
    $message = array(
        'usage' => "Usage: $program <foo.json>",
        'error' =>
        "The command '$program' takes an another argument for configuration path!",
    );

    $value = array_search($program, $args);
    $new_path = $args[++$value] ?? null;

    if (!$new_path) {
        echo $message['usage'] . PHP_EOL;
        echo $message['error'] . PHP_EOL;
        exit;
    }

    // json file path
    return $new_path;
}

function main(array $args): void
{
    $defined_cmds = array('newconfig' => 'newconfig');
    if (has_newconfig($defined_cmds['newconfig'], $args)) {
        $new_path = newconfig($defined_cmds['newconfig'], $args);        
        if (!is_null($new_path) && confirm($new_path)) {
            generate_template($new_path);
        }
        return;
    } else {
        run();
    }
}
function confirm(string $json_name): bool
{
    $message = array(
        'confirm_override' =>
        "$json_name seems to already exists. " .
            "Are you sure to override the file? (y/N)",
        'confirm_override_2' =>
        "Are you *really* sure to override the current $json_name? (y/N)",
        'confirm' =>
        "Are you sure to make a new configuration to $json_name? (y/N)",
    );

    if (file_exists($json_name)) {
        echo "{$message['confirm_override']}: ";
        $input = trim(stream_get_contents(STDIN, 1));

        if ($input === "y") {
            echo PHP_EOL . "{$message['confirm_override_2']}: ";
            $input = trim(stream_get_contents(STDIN, 1));

            if ($input === "y") {
                return true;
            }
        }

        echo 'N';
    } else {
        echo "{$message['confirm']}: ";
        $input = trim(stream_get_contents(STDIN, 1));

        if ($input === "y") {
            return true;
        }
    }

    return false;
}

function generate_template(string $filename): void
{
    $template_data = json_template();

    if (!$fp = fopen($filename, 'w')) {;
        echo PHP_EOL . "Cannot open file $filename";
        exit;
    }

    if (!fwrite($fp, $template_data)) {
        echo PHP_EOL . "Cannot write to file $filename";
        exit;
    }

    if (file_exists($filename)) {
        echo PHP_EOL .
            "Yay! wrote a template to" . PHP_EOL . realpath($filename) . "!";
    }

    fclose($fp);
}

function json_template(): string
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
            'checkpoint' => array(
                0 => array(
                    'meta' => array(
                        'comment' => '',
                        'enabled' => false,
                    ),
                    'baseDirectory' => '',
                    'weightsList' => array(0 => ''),
                    'ignoreList' => array(0 => ''),
                ),
            ),
            'vae' => array(
                0 => array(
                    'meta' => array(
                        'comment' => '',
                        'enabled' => false,
                    ),
                    'baseDirectory' => '',
                    'weightsList' => array(0 => ''),
                    'ignoreList' => array(0 => ''),
                ),
            ),
            'embeddings' => array(
                0 => array(
                    'meta' => array(
                        'comment' => '',
                        'enabled' => false,
                    ),
                    'baseDirectory' => '',
                    'weightsList' => array(0 => ''),
                    'ignoreList' => array(0 => ''),
                ),
            ),
            'hypernetworks' => array(
                0 => array(
                    'meta' => array(
                        'comment' => '',
                        'enabled' => false,
                    ),
                    'baseDirectory' => '',
                    'weightsList' => array(0 => ''),
                    'ignoreList' => array(0 => ''),
                ),
            ),
            'lora' => array(
                0 => array(
                    'meta' => array(
                        'comment' => '',
                        'enabled' => false,
                    ),
                    'baseDirectory' => '',
                    'weightsList' => array(0 => ''),
                    'ignoreList' => array(0 => ''),
                ),
            ),
        ),
    );

    $json_encoded = json_encode(
        $template,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );
    return $json_encoded;
}

function determine_config_parameters(): array
{
    $options_got = getopt('', ['json:']);
    $json_path = $options_got['json'] ?? false;

    // Check if .json is available but otherwise exit
    try {
        if (!$json_path) {
            throw new Exception(
                "Usage: add \"--json PATH\" to specify a config file" . PHP_EOL
            );
        }

        if (!file_exists($json_path)) {
            throw new Exception(Message::JSON_BAD_PATH . PHP_EOL);
        }

        $params = json_decode(file_get_contents($json_path), true) ?? false;
    } catch (Exception $e) {
        echo $e->getMessage();
        exit;
    }

    // Check if .json is valid but otherwise exit
    try {
        if (!$params) {
            throw new Exception(Message::JSON_BAD_CONTENT);
        }
    } catch (Exception $e) {

        echo $e->getMessage();
        exit;
    }

    return $params;
}

function get_key_list(): array
{
    return array('checkpoint', 'vae', 'embeddings', 'hypernetworks', 'lora');
}

/**
 * Join string into a single URL string.
 *
 * @param string $parts,... The parts of the URL to join.
 * @return string The URL string.
 */
function join_paths(string ...$parts): string
{
    if (sizeof($parts) === 0) {
        return '';
    }

    $prefix = ($parts[0] === DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : '';
    $processed = array_filter(
        array_map(fn ($part) => rtrim($part, DIRECTORY_SEPARATOR), $parts),
        fn ($part) => !empty($part)
    );

    return $prefix . implode(DIRECTORY_SEPARATOR, $processed);
}

function source_walk(): array
{
    $json_params = determine_config_parameters();
    $source = $json_params['source'];
    $key_list = get_key_list();
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
                    if (empty($weight)) {
                        continue;
                    }

                    $base_directory = $weights['baseDirectory'];
                    $operation_list['link'][] = array(
                        "src" => join_paths($base_directory, $weight),
                        "dest" => join_paths(
                            callback_which_dest($current_key),
                            $weight
                        ),
                    );
                }

                # unlink
                if (empty($ignore_List)) {
                    continue;
                }
                foreach ($ignore_List as $weight) {
                    if (empty($weight)) {
                        continue;
                    }
                    $operation_list['unlink'][] =
                        join_paths(callback_which_dest($current_key), $weight);
                }
            } else {

                # unlink
                foreach ($weights_List as $weight) {
                    if (empty($weight)) {
                        continue;
                    }
                    $operation_list['unlink'][] =
                        join_paths(callback_which_dest($current_key), $weight);
                }
            }
        }
    }
    return $operation_list;
}

# returns a proper destination written in the settings
function callback_which_dest(string $key_name): string
{
    # find json description
    $json_params = determine_config_parameters();
    $dest_list = $json_params['destination'];

    # just find a proper key-value (specific path) pairs
    foreach ($dest_list as $current_key => $current_dest) {
        if ($key_name === $current_key) {
            return $current_dest;
        }
    }
}

function link_by_type(string $src, string $dest): void
{
    if (file_exists($dest)) {
        return;
    }

    if (!file_exists($src)) {
        echo ($src), " not found", "\n";
        return;
    }

    $option = getopt('', ['symlink']);
    $is_symlink = array_key_exists('json', $option);

    if (!$is_symlink) {
        weight_hardlink($src, $dest);
        return;
    }

    weight_symlink($src, $dest);
}

function weight_hardlink(string $src, string $dest): void
{
    @link($src, $dest);

    $error = error_get_last() ?? [];
    if ($error['message'] === "link(): Improper link") {
        echo $error['message'], ": ";
        echo "Try adding --symlink option \n";
    }
}

function weight_symlink(string $src, string $dest): void
{
    symlink($src, $dest);
}

function weight_unlink(string $filename): void
{
    if (!file_exists($filename)) {
        return;
    }
    unlink($filename);
    echo ($filename), " not found", "\n";
    #echo "unlink $filename";
}

function run(): void
{
    $op_list = source_walk();

    #var_export($op_list);

    foreach ($op_list['link'] as $path_pair) {
        link_by_type($path_pair['src'], $path_pair['dest']);
    }

    foreach ($op_list['unlink'] as $path) {
        weight_unlink($path);
    }

    printf(
        "Linked %s weights (in disabled: %s weights)" . PHP_EOL,
        count($op_list['link']),
        count($op_list['unlink'])
    );
}

main($argv);
