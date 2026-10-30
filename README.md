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

Both commands accept `--help` and `--dry-run`. The repeatable
`--category CATEGORY` option limits an operation to categories already named
in that configuration's `lookupKeys` list. For example:

```bash
php bin/sync --dry-run --category checkpoint < /path/to/config.yaml
php bin/clear --category checkpoint --category lora < /path/to/config.yaml
```

A category outside `lookupKeys` is an error. `--dry-run` performs configuration
and operation checks and reports the planned work without changing links or the
management database.

## Configuration

The configuration is YAML parsed by
[Symfony YAML 6.4](https://symfony.com/doc/6.4/components/yaml.html). Paths are
literal: `~`, environment variables, and glob patterns are not expanded.
`destination` and `baseDirectory` paths must be absolute. `weightsList` and
`ignoreList` values are relative literal paths below `baseDirectory` and the
category destination; entries containing `..` are rejected. Link names use the
same values under `destination[category]`. Aliases are not supported. Quote
path and filename strings when YAML could interpret them as another type.

`lookupKeys` is required and must select at least one category. Every selected
category needs a list under `source` (use `[]` if it has no groups) and a
non-empty path under `destination`.

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
group's `weightsList` entries are also removal candidates. `clear` considers the
listed `weightsList` and `ignoreList` entries for both enabled and disabled
groups. An omitted `ignoreList` is empty. Editing a setting does not itself
remove links that are no longer listed.

## Safety and failures

- Regular files and directories at a link destination are protected; SD-Linker
  does not overwrite or remove them.
- During `sync`, an unmanaged symbolic link is adopted only when its current
  link target matches the source expected by the configuration. A different
  unmanaged link is a conflict and is left untouched. `clear` refuses to remove
  an unmanaged link.
- A managed link whose current target differs from the database record is
  treated as a manual change and rejected. If the link still matches its
  recorded target but the configured source changed, `sync` updates the link to
  the new source.
- Broken (dangling) symbolic links are checked as links, not mistaken for
  missing paths. They are repaired or removed only when their recorded and
  configured state proves they are managed.
- Before changing anything, the program validates the selected operation set.
  A preflight error ends the whole command without changing links or the
  management database.
- If an operation fails after execution begins, later operations stop. The
  report identifies completed, failed, and not-run operations, and the process
  exits non-zero. Completed work is not guaranteed to be rolled back as a
  group; inspect the report and filesystem before retrying.

There is no glob expansion, link aliasing, or automatic pruning of entries
removed from the configuration. To remove a model, keep its entry available to
`clear` or mark it for removal using the documented group settings and run
`sync`.

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
