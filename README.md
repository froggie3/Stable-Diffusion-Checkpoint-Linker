# SD-Linker

SD-Linker creates and removes symbolic links to Stable Diffusion model files;
it does not copy or modify the model files themselves. It targets GNU/Linux.

## Requirements

- PHP 8.1 or later
- Composer
- PHP PDO with the SQLite driver (`pdo_sqlite`)

## Install

```bash
git clone https://github.com/froggie3/Stable-Diffusion-Checkpoint-Linker.git
cd Stable-Diffusion-Checkpoint-Linker
composer install
```

The first successful operation creates the management database at
`data/links.sqlite` when needed. Keep this file: it records which links SD-Linker
manages. The `data/` directory is ignored by Git.

## Agent skill

The `modelop` skill provides instructions for using SD-Linker from an agent. Install it with [npx skills](https://skills.sh/):

```bash
npx skills add froggie3/Stable-Diffusion-Checkpoint-Linker --skill modelop
```

The interactive command lets you choose the installation target. To install it into a Codex project explicitly, use:

```bash
npx skills add froggie3/Stable-Diffusion-Checkpoint-Linker --skill modelop --agent codex
```

Add `-g` for a global installation, for example `npx skills add froggie3/Stable-Diffusion-Checkpoint-Linker --skill modelop -g`. Use `--list` to inspect the skills published by this repository; from a local checkout, validate discovery with `npx skills add . --list`.

This installs skill instructions only. It does not install SD-Linker, PHP, or Composer; set up the project separately using the [Install](#install) instructions above. The current `npx skills` CLI requires Node.js 22.20 or later.

## Configure and run

Copy `config/example.yaml`, then edit the copy for your model and destination
directories. The programs read YAML from standard input:

```bash
php bin/sync < /path/to/config.yaml
php bin/clear < /path/to/config.yaml
```

`sync` creates or updates the selected links. `clear` removes links that the
management database confirms belong to SD-Linker and that are described by the
selected configuration. It does not search for links omitted from the
configuration.

Both commands accept `--help`, `--dry-run`, and `--verbose` (or `-v`) for
successful-operation details. The repeatable
`--category CATEGORY` option limits an operation to categories already named
in that configuration's `lookupKeys` list. For example:

```bash
php bin/sync --dry-run --category checkpoint < /path/to/config.yaml
php bin/clear --category checkpoint --category lora < /path/to/config.yaml
```

A category outside `lookupKeys` is an error. `--dry-run` performs configuration
and operation checks and reports the planned work without changing links or the
management database.

## Configuration and command reference

The configuration is YAML parsed by
[Symfony YAML 6.4](https://symfony.com/doc/6.4/components/yaml.html). Paths are
literal: `~`, environment variables, and glob patterns are not expanded.
`destination` and `baseDirectory` paths must be absolute. `weightsList` and
`ignoreList` values are relative paths below `baseDirectory` and the category
destination; entries containing `..` are rejected. Quote paths and filenames
when YAML could interpret them as another type.

See `php bin/sync --help` or `php bin/clear --help` for the full key schema,
defaults, selection behavior, safety rules, output streams, and exit codes.
Help prints without reading standard input or opening the database.

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

An enabled group's `weightsList` entries are kept linked by `sync`. An enabled
group's `ignoreList` entries are removal candidates during `sync`; a disabled
group's `weightsList` entries are also removal candidates. `clear` considers
both lists for enabled and disabled groups. An omitted `ignoreList` is empty.

## Development

Run the test suite with:

```bash
composer test
```

Tests use temporary files and isolated databases. Run the project checks with:

```bash
composer check
```

Use `composer lint` for PHPStan and PHP syntax checks, `composer format:check` to
see formatting differences, and `composer format` to apply the configured
formatter.
