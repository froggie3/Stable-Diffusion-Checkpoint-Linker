#!/usr/bin/php
<?php

declare(strict_types=1);

class Message
{
    const UNLINKED_OK = 'リンクを削除しました';
    const LINKED_OK = 'リンクを作成しました';
    const JSON_BAD_CONTENT = '不正な設定ファイルです';
    const JSON_BAD_PATH = '指定されたJSONファイルのパス名が不正です';
}

function determine_config_parameters(): array
{
    $option = new Option;
    $params = array();
    $options_got = getopt(short_options: 'hr', long_options: ['json:']);
    $json_path = ($option->has_json($options_got))
        ? $options_got['json']
        : 'C:\mnt1\yokkin\Documents\GitHub\Stable-Diffusion-Checkpoint-Linker\config\config.json';

    // Check if .json is available but otherwise exit
    try {
        if (!file_exists($json_path)) {
            throw new Exception(Message::JSON_BAD_PATH);
        }
        $params = json_decode(file_get_contents($json_path), true);
    } catch (Exception $e) {
        echo $e->getMessage();
        exit;
    }

    // Check if .json is valid but otherwise exit
    try {
        if (is_null($params)) {
            throw new Exception(Message::JSON_BAD_CONTENT);
        }
    } catch (Exception $e) {
        echo $e->getMessage();
        exit;
    }

    return $params;
}

/*
 * クラスの初期化
 */


#var_dump($first_elem);

function get_key_list(): array
{
    return array('checkpoint', 'vae', 'embeddings', 'hypernetworks');
}

/**
 * Join string into a single URL string.
 *
 * @param string $parts,... The parts of the URL to join.
 * @return string The URL string.
 */
function join_paths(string ...$parts)
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

function source_walk()
{
    $json_params = determine_config_parameters();
    $source = $json_params['source'];
    $key_list = get_key_list();
    $operation_list = array("link" => array(), "unlink" => array());

    foreach ($key_list as $current_key) {
        #echo $current_key, "\n";
        $elem = $source[$current_key];

        # walking around json
        foreach ($elem as $weights) {
            $weights_List = $weights['weightsList'] ?? [];
            $ignore_List = $weights['ignoreList'] ?? [];
            $weights_enabled = $weights['meta']['enabled'] || false;

            #var_dump($weights_enabled);
            if ($weights_enabled && !empty($weights_List)) {
                # "weightLists"
                foreach ($weights_List as $weight) {
                    if (empty($weight)) {
                        continue;
                    }

                    $base_directory = $weights['baseDirectory'];
                    $operation_list['link'][] = array(
                        "src" => join_paths($base_directory, $weight),
                        "dest" => join_paths(callback_which_dest(key_name: $current_key), $weight),
                    );
                }
                #unlink
                if (empty($ignore_List)) {
                    continue;
                }

                foreach ($ignore_List as $weight) {
                    if (empty($weight)) {
                        continue;
                    }

                    $operation_list['unlink'][] = join_paths(callback_which_dest(key_name: $current_key), $weight);
                }
            } else {
                # unlink
                foreach ($weights_List as $weight) {
                    if (empty($weight)) {
                        continue;
                    }

                    $operation_list['unlink'][] = join_paths(callback_which_dest(key_name: $current_key), $weight);
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

/*
 * コマンドライン引数をパース
 */

class Option
{
    // --json オプションがあるか？
    public function has_json(array $options): bool
    {
        return (isset($options['json']))
            ? true
            : false;
    }

    // --symlink オプションがあるか？
    public function has_symlink(array $options): bool
    {
        return (isset($options['symlink']))
            ? true
            : false;
    }
}

function link_by_type(string $src, string $dest): void
{
    if (file_exists($dest)) {
        return;
    }

    $exists = array($src => file_exists($src));
    foreach ($exists as $path => $bool) {
        if (!$bool) {
            echo ($path), " not found", "\n";
            #continue;
            return;
        }
    }

    if (!(new Option)->has_symlink(getopt('', ['symlink']))) {
        weight_hardlink($src, $dest);
        #echo "hard link!", "\n";
        return;
    }
    #echo "sym link!", "\n";
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

    // link_by_type と処理が被っているので共通化したいね
    $exists = array($filename => file_exists($filename));
    foreach ($exists as $path => $bool) {
        if (!$bool) {
            echo ($path), " not found", "\n";
            continue;
            return;
        }
    }

    unlink($filename);
    #echo "unlink $filename";
}

function main()
{
    $op_list = source_walk();
    #var_export($op_list);
    foreach ($op_list['link'] as $path_pair) {
        link_by_type($path_pair['src'], $path_pair['dest']);
    }
    foreach ($op_list['unlink'] as $path) {
        #echo "unlink $path", "\n";
        weight_unlink($path);
    }
    printf(
        "Linked %s weights (in disabled: %s weights)",
        count($op_list['link']),
        count($op_list['unlink'])
    );
}

main();
