<?php

declare(strict_types=1);

/*
 * メイン処理を呼び出すためのランチャー的なもの
 */

function main()
{

    $short_options = 'h';
    $short_options .= 'r';
    $long_options = ['unlink', 'json:'];
    $options = getopt($short_options, $long_options);

    if (in_array('json', $options, true)) {
        // 指定された *.json のディレクトリを代入
        $json_dir = $options['json'];
        print '*.json を代入しました';
    }

    if (in_array('unlink', $options, true)) {
        // if --unlink option given 
        $is_unlink = 1;
        print '--unlink オプションが指定されました';
    } else {
        $is_unlink = 0;
    }

    echo 'Hello world!', "\n";
    print_r($options, false);
}



if ($argc >= 2 && in_array($argv[1], ['--help', '-help', '-h', '-?'])) {
    #|| $argc >= 2 && !in_array($argv[1], ['--unlink'])
?>

    This is a command line tool to make hardlinks for the requirements
    of Stable Diffusion Webui, such as *.ckpt, *.pt, *.safetensors, and etc.

    Usage:
    <?php echo $argv[0]; ?> <option>

        -h, --help show this help message and exit
        -r, --unlink remove all the hardlinks
        --json [...PATH] specify *.json file to load

    <?php

    main();
} else {
    main();
}
