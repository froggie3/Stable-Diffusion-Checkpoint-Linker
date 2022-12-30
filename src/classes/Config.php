<?php

declare(strict_types=1);

namespace classes;

/*
 * コンフィグパース用
 */

class Config
{
    public $webui_dir;
    public $cfg_array;

    public function __construct(array $params)
    {
        $Prettier = new Prettier;

        $this->webui_dir = $Prettier->doAll($params['webui']);
        $this->cfg_array = $params['configs'];
    }
}
