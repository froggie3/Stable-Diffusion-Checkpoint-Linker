<?php

declare(strict_types=1);

namespace classes;

// class to delete unwanted characters in configs
class Prettier
{
    public function doAll($target): string
    {
        if (is_array($target)) {
            // string が出てくるまで再帰する
            foreach ($target as $item) {
                return $this->doAll($item);
            }
        } else {
            $string = "";

            $string = $this->removeBothEndsBackslash($this->removeInnerBackslash($target));
            return $string;
        }
    }

    public function removeInnerBackslash(string $target): string
    {
        $string = "";

        $string = str_replace('\\', '/', str_replace('..', '', $target));
        return $string;
    }

    public function removeBothEndsBackslash(string $target): string
    {
        $array = [];
        $array_tmp = [];
        $sizeof = 0;

        $array = str_split($target); // unneeded '/' on head and tail
        $sizeof = count($array) - 1;

        // Trim if the last item is '/'
        if ($array[$sizeof] === '/') {
            $sizeof = $sizeof - 1;
        }

        // Trim if the first item is '/'
        for ($i = ($array[0] === '/') ? 1 : 0; $i <= $sizeof; $i++) {
            $array_tmp[] = $array[$i];
        }
        return implode('', $array_tmp);
    }
}
