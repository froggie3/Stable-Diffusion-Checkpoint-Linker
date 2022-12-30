<?php

declare(strict_types=1);

namespace classes;

/*
 * パスの操作
 */

class Path
{
    public $target_directory;

    public function __construct(string &$target_directory)
    {
        $this->target_directory = $target_directory;
    }

    // ディレクトリのみのリストを返す
    public function extract_dir(): array
    {
        $dir_extracted = [];
        $dir_list = array_slice(scandir($this->target_directory, 0), 2);

        foreach ($dir_list as $item) {
            if (is_dir($this->target_directory . '/' . $item) === true) {
                $dir_extracted[] = $item;
            }
        }
        return $dir_extracted;
    }

    // *.pt のみのリストを返す
    public function extract_pt(): array
    {
        chdir($this->target_directory);
        return glob('*.pt');
    }

    // ディレクトリ + ファイルのリストを返す
    public function get_dir(): array
    {
        return array_diff(scandir($this->target_directory, 0), ['..', '.']);
    }
}
