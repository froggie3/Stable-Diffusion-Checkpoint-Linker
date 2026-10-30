# SD-Linker

Create and remove symbolic links to Stable Diffusion model files on GNU/Linux.

## Install

PHP 8.1 or later, PDO SQLite, and Composer are required.

```bash
git clone https://github.com/froggie3/Stable-Diffusion-Checkpoint-Linker.git
cd Stable-Diffusion-Checkpoint-Linker
composer install
```

## Configuration

Configuration is parsed with Symfony YAML. YAML comments and sequences with or
without additional indentation below their key are supported. `lookupKeys` is
required and must contain at least one unique, non-empty category name. Each
selected category must exist in both `source` and `destination`.

```yaml
lookupKeys:
  - checkpoint
destination:
  checkpoint: /srv/stable-diffusion/models/Stable-diffusion
source:
  checkpoint:
    - meta:
        enabled: true
      baseDirectory: /srv/models/checkpoints
      weightsList:
        - example.safetensors
      ignoreList: []
```

Copy `config/example.yaml` and edit it for your directories. The commands read
configuration from standard input:

```bash
php bin/sync < /path/to/config.yaml
php bin/clear < /path/to/config.yaml
```

Invalid configuration is reported to stderr with a non-zero exit status before
the management database is opened. Run configuration tests with `composer test`.
