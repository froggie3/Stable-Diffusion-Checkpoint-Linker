# SD-Linker

![images/20230406010745.png](images/20230406014524.png)

Using this utility enables you to make a a symlink of various weight
files used in Stable Diffusion Web UI (`*.safetensors`, `*.ckpt`, `*.pt`, or
something like that) without copying weights from their original folders.  
Since this will just make no real copy of files, making links or its deletion 
is really fast, whereas model files are usually a big deal for SSD or HDD.

This utility requires [PHP](https://www.php.net/) to be installed.

## Setup

Clone this repository.

```
git clone
cd Stable-Diffusion-Checkpoint-Linker
```

Resolve dependencies for this tool. Assuming you have Composer (a Dependency Manager for PHP) globally installed to your machine.

```bash
composer install
```

## How to use

This application requires a configuration file in YAML format to make symbolic links to files.
If it is not ready, you can refer to `Making a template for config file` section later.
This is referred as `YOUR_CONFIG.yaml`.

To make symbolic links, run the command with:

```bash
bin/sync < YOUR_CONFIG.yaml
```

To unlink:

```bash
bin/clear < YOUR_CONFIG.yaml
```

## Config file

Before you work on this program you need to prepare the configuration.
Just open `config/example.yaml` in your text editor, and start setting the 
installation directory for Stable Diffusion Web UI and weights used by the UI.

Config file is roughly separated into two sections: `desination` and `source`.
In `destination`, you can specify the destination for symbolic links or 
hardlinks to real weights to be placed, while `source` is for the location for
original weights living in.

`source` section is also devided into some sections so you can tell the type
of weights from others. The types are like: `checkpoint`, `vae`, `embeddings`, 
`hypernetworks`, `lora`, `controlnet`.

### Schema

Note that since the configuration in written in JSON format, the comment in 
this example is not anything for the parser to understand.
Please eliminate them while you edit the configuration. 

```yaml
destination:
    webui: C:/foo/stable-diffusion-webui
    checkpoint: C:/foo/stable-diffusion-webui/models/Stable-diffusion
    vae: C:/foo/stable-diffusion-webui/models/VAE
    embeddings: C:/foo/stable-diffusion-webui/embeddings
    hypernetworks: C:/foo/stable-diffusion-webui/models/hypernetworks
    lora: C:/foo/stable-diffusion-webui/models/Lora
    controlnet: C:/foo/stable-diffusion-webui/models/Lora
source:
    checkpoint:
        - meta:
              # Setting this to 'false' causes all the models in entry 'weightList'
              # to be unlinked from the destination.
              enabled: true
          baseDirectory: C:/foo
          weightsList:
              - foo.safetensors
          ignoreList:
              # Moving models on 'ignoreList' also allows discrete control
              # to a model availability.
              - baz.safetensors
        - meta:
              enabled: false
          baseDirectory: C:/bar
          weightsList:
              - bar.safetensors
          # You can omit 'ignoreList' section since it is optional.
          # ignoreList:
          #    - ""
    vae:
        # same as above
    embeddings:
        # same as above
    hypernetworks:
        # same as above
    lora:
        # same as above
    controlnet:
        # same as above
```

## Misc

### Environments

```plain
> php --version
PHP 8.1.2-1ubuntu2.17 (cli) (built: May  1 2024 10:10:07) (NTS)
Copyright (c) The PHP Group
Zend Engine v4.1.2, Copyright (c) Zend Technologies
    with Zend OPcache v8.1.2-1ubuntu2.17, Copyright (c), by Zend Technologies
```

### References

- <https://github.com/AUTOMATIC1111/stable-diffusion-webui>
