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
        function call(string $target_dir): string {
            if (!file_exists($target_dir)) {
                // 再帰的にディレクトリを作成
                mkdir(directory: $target_dir, recursive: true);
            }

            // スラッシュの処理
            return (new Prettier)->fix_slash($target_dir);
        }

        $this->webui_dir = call($params['webui']);
        $this->ckpt_dir = call($params['ckpt-dir']);
        $this->embeddings_dir = call($params['embeddings-dir']);
        $this->hypernetwork_dir = call($params['hypernetwork-dir']);
        $this->vae_path = call($params['vae-path']);
        /*
        $this->cfg_array = array_map(
            fn() => (new Prettier())->doAll($params['configs']),
            $params['configs']
        );
        */
        $this->cfg_array = $params['configs'];
    }
}
