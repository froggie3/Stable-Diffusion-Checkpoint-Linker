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
        $this->webui_dir = (new Prettier())->doAll($params['webui']);
        /*
        $this->cfg_array = array_map(
            fn() => (new Prettier())->doAll($params['configs']),
            $params['configs']
        );
        */
        $this->cfg_array = $params['configs'];
    }
}
