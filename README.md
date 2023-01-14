# stable_diffusion_setup.php

Stable Diffusion Webui で使用する各モデルを元のフォルダからコピーせずにハードリンクを貼ってくれるスクリプト。ハードリンクなのでいちいち大きいファイルをコピーをしたり、手動でデータを管理する手間を大幅に省くことができます。

実行には PHP が動く環境が必要です。  
https://www.php.net/

# 使い方

スクリプトを何もオプションを付けずに実行するとリンクを貼りに行きます。

```
php .\stable_diffusion_setup.php
```

## ハードリンクを削除する

ハードリンクを削除するときは、`--unlink` オプションを付けて実行してください。

```
php .\stable_diffusion_setup.php --unlink
```

## お好みの `*.json` を指定する

`--json` オプションを利用すると、指定したパス名の設定ファイルにアクセスし、その設定ファイルからリンクを貼ります。  
特に指定がない場合はデフォルトの `config\config.json` を読みます。

`--unlink` と同時に使用するときは、指定した設定ファイルを基にしてハードリンクを削除します。

```bash
php .\stable_diffusion_setup.php --json config\config.json
```

## シンボリックリンクを貼る

`--symlink` を付与して実行すると、ハードリンクの代わりにシンボリックリンクを貼りに行きます。
ボリュームをまたいだリンクを貼ることができますが、管理者権限のターミナルで実行する必要があります。

# 設定

まず最初に、`/config/config.json` を開き、Stable Diffusion Web UI の インストールディレクトリを設定します。

以下に設定例を示します。

```json
{
    "webui": "C:\\std\\stable-diffusion-webui\\",
    "configs": [
        {
            "ckpt_dir": "C:\\std\\novelaileak\\stableckpt\\animefull-final-pruned\\",
            "model": ["model.ckpt"],
            "vae_dir": "C:\\std\\novelaileak\\stableckpt",
            "vae": ["animevae.pt"],
            "includes_nai_hypernetworks": true,
            "hn_dir": "C:\\std\\novelaileak\\stableckpt\\modules\\modules"
        },
        {
            "ckpt_dir": "C:\\std\\anything-v3-0",
            "model": [
                "Anything-V3.0.ckpt",
                "Anything-V3.0-pruned-fp16.ckpt",
                "Anything-V3.0-pruned-fp32.ckpt"
            ],
            "vae_dir": "C:\\std\\anything-v3-0",
            "vae": ["Anything-V3.0.vae.pt"]
        },
        {
            "embeddings_dir": "C:\\std\\embeddings\\bad-artist",
            "embeddings": ["bad-artist.pt", "bad-artist-anime.pt"]
        }
    ]
}
```

-   **ckpt**
    -   `ckpt_dir`  
        モデルのあるディレクトリを指定します。区切り文字は必要ありません。
    -   `model`  
        checkpoint ファイルを複数指定できます。
-   **VAE**
    -   `vae_dir`  
        VAE があるディレクトリを指定します。
    -   `vae`  
        VAE を複数指定できます。
-   **Embedding**
    -   `embeddings_dir`  
        Embedding があるディレクトリを指定します。
    -   `embeddings`  
        Embedding を複数指定できます。

以下は仕様が変更するかもしれないので注意

-   `includes_nai_hypernetworks`  
    NAI の HyperNetwork がある場合、これを`true`に設定します。
-   `hn_dir`  
    その場合、`.pt`ファイルのあるディレクトリを指定します。

# その他情報

## 開発環境

```
> php --version
PHP 8.1.11 (cli) (built: Sep 28 2022 11:08:01) (NTS Visual C++ 2019 x64)
Copyright (c) The PHP Group
Zend Engine v4.1.11, Copyright (c) Zend Technologies
    with Zend OPcache v8.1.11, Copyright (c), by Zend Technologies
    with Xdebug v3.1.5, Copyright (c) 2002-2022, by Derick Rethans
```

## Todo

-   シンボリック先の名前を変更する機能を設ける

## 参考

-   https://github.com/AUTOMATIC1111/stable-diffusion-webui
