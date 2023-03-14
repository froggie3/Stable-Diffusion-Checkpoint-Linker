# SD-Linker 

This script hard links each model used in Stable Diffusion Web UI without copying from the original folder. This hard-linking eliminates the need to copy large files and manually manage data.

PHP environment is required to run this script.

<https://www.php.net/>

## How to use

To make the hard link or symbolic link, run the script with `--json` options giving the path to the configuration file.
If not specified, the default `config/config.json` is read.

```bash
.\run.ps1 --json config/config.json
```

### Making symbolic link instead of hard link

If you give `--symlink` and run it, it will go put a symbolic link instead of a hard link.
It enables you to put a link across volumes, but it must be run in a terminal with administrative privileges.

## Setting

First, open `config/config.json` and set the installation directory for Stable Diffusion Web UI.

... to be written

## Misc

### Environments

```bash
> php --version
PHP 8.2.3 (cli) (built: Feb 14 2023 09:54:05) (NTS Visual C++ 2019 x64)
Copyright (c) The PHP Group
Zend Engine v4.2.3, Copyright (c) Zend Technologies
```

### Todo

- Provide the ability to change the name of the symbolic destination

### References

- <https://github.com/AUTOMATIC1111/stable-diffusion-webui>
