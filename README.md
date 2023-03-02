# stable_diffusion_setup.php

This script hard links each model used in Stable Diffusion Web UI without copying from the original folder. This hard-linking eliminates the need to copy large files and manually manage data.

PHP environment is required to run this script.

<https://www.php.net/>

## How to use

To make the hard/symbolic link, run the script with no arguments.

```bash
php .\stable_diffusion_setup.php
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

... to be written

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
