<?php

declare(strict_types=1);

namespace classes;

/*
 * コンフィグパース用
 */

class Config
{
    public $webui_dir;
    public $ckpt_dir;
    public $embeddings_dir;
    public $hypernetwork_dir;
    public $vae_path;
    public $cfg_array;

    public function __construct(array $params)
    {
        function exists(string $target_dir): string {
            if (! file_exists($target_dir)) {
                exit;
            }
            return $target_dir; 
        }

        $this->webui_dir = exists($params['webui']);
        $this->ckpt_dir = exists($params['ckpt-dir']);
        $this->embeddings_dir = exists($params['embeddings-dir']);
        $this->hypernetwork_dir = exists($params['hypernetwork-dir']);
        $this->vae_path = exists($params['vae-path']);
        /*
        $this->cfg_array = array_map(
            fn() => (new Prettier())->doAll($params['configs']),
            $params['configs']
        );
        */
        $this->cfg_array = $params['configs'];
    }
}
