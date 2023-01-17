#!/usr/bin/php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

/* オプション用 */
$options = getopt(
    short_options: 'hr',
    long_options: ['json:',]
);

function determine_config_parameters(array &$options): array
{
    $Option = new classes\Option;
    $params = array();

    $json_path = ($Option->has_json($options))
        ? $options['json']
        : './config/config.json';

    // Check if .json is available but otherwise exit
    if (!file_exists($json_path)) {
        error_log(classes\Message::JSON_BAD_PATH);
        exit;
    }

    $params = json_decode(file_get_contents($json_path), true);

    // Check if .json is valid but otherwise exit
    if (is_null($params)) {
        error_log(classes\Message::JSON_BAD_CONTENT);
        exit;
    }

    return $params;
}

/*
 * クラスの初期化
 */

$Prettier = new classes\Prettier;
$Config = new classes\Config(determine_config_parameters($options));
$Execute = new classes\Execute;

/*
 * 主要な処理
 */


# 見通しが悪いのをどうにかする
# destination の決定は、クラス側で決定した方が良いかも
# foreach の入力にあたる配列は固定化して、関数の戻り値を受け取るようにする

foreach ($Config->cfg_array as $item) {
    // Checkpoints
    if ((isset($item['model'])) && ($item['model'] !== [])) {
        foreach ($item['model'] as $filename) {
            $Execute->execute(
                src: $item['ckpt_dir'] . '/' . $filename,
                dest: $Config->ckpt_dir . $filename
            );
        }
    }
    // VAE
    if ((isset($item['vae'])) && ($item['vae'] !== [])) {
        foreach ($item['vae'] as $filename) {
            $Execute->execute(
                src: $item['vae_dir'] . '/' . $filename,
                dest: $Config->vae_path . $filename
            );
        }
    }
    // Embeddings
    if ((isset($item['embeddings'])) && ($item['embeddings'] !== [])) {
        foreach ($item['embeddings'] as $filename) {
            $Execute->execute(
                src: $item['embeddings_dir'] . '/' . $filename,
                dest: $Config->embeddings_dir . $filename
            );
        }
    }
    // HyperNetworks 
    if ((isset($item['hypernetworks'])) && ($item['hypernetworks'] !== [])) {
        foreach ($item['hypernetworks'] as $filename) {
            $Execute->execute(
                src: $item['hn_dir'] . '/' . $filename,
                dest: $Config->hypernetwork_dir . $filename
            );
        }
    }
    // HyperNetworks for NovelAI
    if (isset($item['includes_nai_hypernetworks']) && $item['includes_nai_hypernetworks']) {
        $dir_prefix = $Prettier->doAll($item['hn_dir']);
        $Path = new classes\Path($dir_prefix);

        foreach ($Path->extract_pt() as $filename) {
            $Execute->execute(
                src: $dir_prefix . '/' . $filename,
                dest: $Config->hypernetwork_dir. $filename
            );
        }
        unset($Path);
    }
}

// 最後にメッセージを表示する
function show_message(int &$is_unlink): string
{
    return ($is_unlink === 0)
        ? classes\Message::LINKED_OK . "\n"
        : classes\Message::UNLINKED_OK . "\n";
}

#echo show_message($is_unlink);
unset($Config, $Execute, $Prettier);
