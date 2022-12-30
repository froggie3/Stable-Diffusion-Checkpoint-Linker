<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

/*
 * ディレクトリをリスティングし、リスティングされたディレクトリに対して再帰処理を適用する
 */

require './classes.php';

// 変数の初期化
$target_directory = 'C:/std/HyperNetworkCollection_v2/_Korea_arca.live_HypernetworkCollection';
chdir($target_directory);

function print_recursive_dirname_alt(string $target_directory)
{
    // クラスの初期化
    $Path = new classes\Path($target_directory);

    // ソートして出力 
    $file_only = $Path->extract_pt();
    sort($file_only);

    /*
     * サブディレクトリの個数を確認
     * 
     * 個数が 0 ならループを終了し、
     * そうでないならリストaに個数を格納 & リストbにディレクトリ名を格納 & ディレクトリ名から再帰呼び出し
     */

    (int) $dirs = count($Path->extract_dir());

    /*
    * *.ckpt の個数を確認
    * 
    * 個数が 0 ならなにもせず、そうでないならそのディレクトリにあるすべての *.ckpt を処理
    */

    echo "\n", '■■■■■■■■■■■■ *.ckpt を処理します ■■■■■■■■■■■■■■', "\n";
    /*foreach ($file_only as $item) {
        echo $item, ", ";
    }
    echo "\n";*/
    print_r($file_only);

    /*
     * サブディレクトリの個数を確認
     * 
     * 個数が 0 ならループを終了し、
     * そうでないならリストaに個数を格納 & リストbにディレクトリ名を格納 & ディレクトリ名から再帰呼び出し
     */

    print_r($Path->extract_dir());

    if ($dirs === 0) {
        return 0;
    } else {
        foreach ($Path->extract_dir() as $a_dirname) {
            return print_recursive_dirname_alt($target_directory . '/' . $a_dirname);
        }
    }
}

function print_recursive_dirname(string $target_directory)
{
    $Path = new classes\Path($target_directory);

    $list = [];

    foreach ($Path->get_dir() as $item) {
        if (is_file($item)) {
            $list[] = $item;
        }
        if (is_dir($item)) {
            $list = array_merge($list, print_recursive_dirname($target_directory . '/' . $item));
        }
    }

    #print_r($list);
    return $list;
}


print_r(print_recursive_dirname($target_directory));
