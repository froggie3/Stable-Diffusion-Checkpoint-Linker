# stable_diffusion_setup.php

This script hard links each model used in Stable Diffusion Web UI without copying from the original folder. This hard-linking eliminates the need to copy large files and manually manage data.

PHP environment is required to run this script.

<https://www.php.net/>

## How to use

To make the hard link, run the script with no arguments.

```bash
php .\stable_diffusion_setup.php
```

### Deleting hard link

To remove hard links, run the script with the `--unlink` argument.

```bash
php .\stable_diffusion_setup.php --unlink
```

### Specify your preferred `*.json` file

- The `--json` option try to access the configuration file with the specified pathname and links from that file.  
If not specified, the default `config\config.json` is read.

- With it used with `--unlink`, hard links are removed based on the specified configuration file with the argument.

```bash
php .\stable_diffusion_setup.php --json config\config.json
```

### Making symbolic link instead of hard link

If you give `--symlink` and run it, it will go put a symbolic link instead of a hard link.
It enables you to put a link across volumes, but it must be run in a terminal with administrative privileges.

## Setting

First, open `/config/config.json` and set the installation directory for Stable Diffusion Web UI.

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

- **ckpt**
  - `ckpt_dir`  
        specifies the directory where the model is located. No slash is required.
  - `model`  
        can specifies multiple checkpoint files.
- **VAE**
  - `vae_dir`  
        specifies the directory where VAE is located.
  - `vae`  
        can specify multiple VAEs.
- **Embedding**
  - `embeddings_dir`  
        specify the directory where Embedding is located.
  - `embeddings`  
        can specify multiple Embeddings.

Note that the following specifications may change:

- `includes_nai_hypernetworks`  
    If NAI's HyperNetwork is present, set this to `true`.
- `hn_dir`  
    In that case, specify the directory where the `.pt` file is located.

## Misc

### Environments

```bash
> php --version
PHP 8.1.11 (cli) (built: Sep 28 2022 11:08:01) (NTS Visual C++ 2019 x64)
Copyright (c) The PHP Group
Zend Engine v4.1.11, Copyright (c) Zend Technologies
    with Zend OPcache v8.1.11, Copyright (c), by Zend Technologies
    with Xdebug v3.1.5, Copyright (c) 2002-2022, by Derick Rethans
```

### Todo

- Provide the ability to change the name of the symbolic destination

### References

- <https://github.com/AUTOMATIC1111/stable-diffusion-webui>
