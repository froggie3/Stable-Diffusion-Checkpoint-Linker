#!/usr/bin/php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

/*
 * オプション用の関数
 */

$options = getopt('hr', ['unlink', 'json:']);

function determine_operation(array &$options): int
{
    $Option = new classes\Option;
    return $Option->is_unlink($options);
}

(int) $is_unlink = determine_operation($options);

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

foreach ($Config->cfg_array as $item) {

    // Checkpoints
    if ((isset($item['model'])) && ($item['model'] !== [])) {
        $sourcedir = $Prettier->doAll($item['ckpt_dir']);

        foreach ($item['model'] as $filename) {
            $filename = $Prettier->doAll($filename);

            $TMP_SOUR = $sourcedir . '/' . $filename;
            $TMP_DEST = $Config->webui_dir . '/models/Stable-diffusion/' . $filename;

            $Execute->execute($TMP_SOUR, $TMP_DEST, $is_unlink);
        }
    }

    // VAE
    if ((isset($item['vae'])) && ($item['vae'] !== [])) {
        $sourcedir = $Prettier->doAll($item['vae_dir']);

        foreach ($item['vae'] as $filename) {
            $filename = $Prettier->doAll($filename);

            $TMP_SOUR = $sourcedir . '/' . $filename;
            $TMP_DEST = $Config->webui_dir . '/models/VAE/' . $filename;

            $Execute->execute($TMP_SOUR, $TMP_DEST, $is_unlink);
        }
    }

    // Embeddings
    if ((isset($item['embeddings'])) && ($item['embeddings'] !== [])) {
        $sourcedir = $Prettier->doAll($item['embeddings_dir']);

        foreach ($item['embeddings'] as $filename) {
            $filename = $Prettier->doAll($filename);

            $TMP_SOUR = $sourcedir . '/' . $filename;
            $TMP_DEST = $Config->webui_dir . '/embeddings/' . $filename;

            $Execute->execute($TMP_SOUR, $TMP_DEST, $is_unlink);
        }
    }

    // HyperNetworks 
    if ((isset($item['hypernetworks'])) && ($item['hypernetworks'] !== [])) {
        $sourcedir = $Prettier->doAll($item['hn_dir']);

        foreach ($item['hypernetworks'] as $filename) {
            $filename = $Prettier->doAll($filename);

            $TMP_SOUR = $sourcedir . '/' . $filename;
            $TMP_DEST = $Config->webui_dir . '/models/hypernetworks/' . $filename;

            $Execute->execute($TMP_SOUR, $TMP_DEST, $is_unlink);
        }
    }

    // HyperNetworks for NovelAI
    if ((isset($item['includes_nai_hypernetworks'])) && ($item['includes_nai_hypernetworks'] === true)) {
        $dir_prefix = $Prettier->doAll($item['hn_dir']);
        $Path = new classes\Path($dir_prefix);

        foreach ($Path->extract_pt() as $filename) {
            $filename = $Prettier->doAll($filename);

            $TMP_SOUR = $dir_prefix . '/' . $filename;
            $TMP_DEST = $Config->webui_dir . '/models/hypernetworks/' . $filename;

            $Execute->execute($TMP_SOUR, $TMP_DEST, $is_unlink);
        }
        unset($Path);
    }
}

// 最後にメッセージを表示する
function show_message(int &$is_unlink): void
{
    if ($is_unlink === 0) {
        echo classes\Message::LINKED_OK, "\n";
    } else {
        echo classes\Message::UNLINKED_OK, "\n";
    }
}

show_message($is_unlink);
unset($Config, $Execute, $Prettier);
