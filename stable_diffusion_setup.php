#!/usr/bin/php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

/*
 * unlink か link かを決定するオプション用の関数
 */

$options = getopt('hr', ['unlink', 'json:', 'symlink']);

function determine_is_unlink(array &$options): int
{
    return (new classes\Option)->is_unlink($options);
}

$is_unlink = determine_is_unlink($options);

/*
 * link か symlink かを決定するオプション用の関数
 */

 function determine_has_symlink(array &$options): bool
 {
    return (new classes\Option)->has_symlink($options);
 }
 
$has_symlink = determine_has_symlink($options);

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
            $Execute->execute(
                $sourcedir . '/' . $filename,
                $Config->webui_dir . '/models/Stable-diffusion/' . $filename,
                $is_unlink,
                $has_symlink
            );
        }
    }

    // VAE
    if ((isset($item['vae'])) && ($item['vae'] !== [])) {
        $sourcedir = $Prettier->doAll($item['vae_dir']);

        foreach ($item['vae'] as $filename) {
            $Execute->execute(
                $sourcedir . '/' . $filename,
                $Config->webui_dir . '/models/VAE/' . $filename,
                $is_unlink,
                $has_symlink
            );
        }
    }

    // Embeddings
    if ((isset($item['embeddings'])) && ($item['embeddings'] !== [])) {
        $sourcedir = $Prettier->doAll($item['embeddings_dir']);

        foreach ($item['embeddings'] as $filename) {
            $Execute->execute(
                $sourcedir . '/' . $filename,
                $Config->webui_dir . '/embeddings/' . $filename,
                $is_unlink,
                $has_symlink
            );
        }
    }

    // HyperNetworks 
    if ((isset($item['hypernetworks'])) && ($item['hypernetworks'] !== [])) {
        $sourcedir = $Prettier->doAll($item['hn_dir']);

        foreach ($item['hypernetworks'] as $filename) {
            $Execute->execute(
                $sourcedir . '/' . $filename,
                $Config->webui_dir . '/models/hypernetworks/' . $filename,
                $is_unlink,
                $has_symlink
            );
        }
    }

    // HyperNetworks for NovelAI
    if (
        isset($item['includes_nai_hypernetworks'])
        && $item['includes_nai_hypernetworks']
    ) {
        $dir_prefix = $Prettier->doAll($item['hn_dir']);
        $Path = new classes\Path($dir_prefix);

        foreach ($Path->extract_pt() as $filename) {
            $Execute->execute(
                $dir_prefix . '/' . $filename,
                $Config->webui_dir . '/models/hypernetworks/' . $filename,
                $is_unlink,
                $has_symlink
            );
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
