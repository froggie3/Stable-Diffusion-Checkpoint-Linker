---
name: modelop
description: "Plan and run SD-Linker sync or clear operations for Stable Diffusion model symlinks using an explicit YAML configuration."
---

# modelop

Use SD-Linker to manage model symlinks without copying or modifying model files. Find an existing linker checkout containing `bin/sync`, `bin/clear`, and `vendor/autoload.php`, and use an explicit YAML configuration. The installed skill directory is not the linker checkout. Set paths for the current task; do not assume a particular checkout or configuration:

```bash
LINKER_ROOT=/path/to/Stable-Diffusion-Checkpoint-Linker
CONFIG=/path/to/config.yaml
```

Call the CLI directly. A compatibility `modelop` wrapper may not forward extra flags such as `--dry-run` or `--category`.

## Plan, execute, verify

For a requested operation, first preview it, then run the same command with the same configuration and category selection, and verify the affected links:

```bash
php "$LINKER_ROOT/bin/sync" --dry-run < "$CONFIG"
php "$LINKER_ROOT/bin/sync" < "$CONFIG"
```

Use `bin/clear` for a clear request. Add the same repeatable `--category NAME` arguments to both preview and execution when the request is category-scoped. Use the CLI preflight for ordinary destination and link checks; do not manually scan every destination with `lstat` or `readlink` before each operation. After execution, check the planned targets and confirm source files remain. If an error, unexpected change, or incomplete operation occurs, inspect the reported targets and current filesystem/database state before deciding what to do next.

Do not insert `clear` before a requested `sync` as a precaution. Do not request approval again for link changes the user has already authorized. Use the approved sandbox elevation flow when access requires it; do not bypass restrictions with manual `ln` or `rm` commands.

## Configuration and scope

The CLI parses the whole YAML document, and configuration loading validates `lookupKeys` and the required types/schema for every category named there. `lookupKeys` must be a non-empty list of unique, non-empty category names. Each listed category needs a `source` list (which may be empty) and a safe absolute `destination`; each source group needs boolean `meta.enabled`, a safe absolute `baseDirectory`, and a `weightsList`. `ignoreList` is optional and defaults to empty. A malformed schema in an unselected category still causes a configuration error. A well-formed category outside the selected operation may refer to source files that are absent without blocking that operation's plan.

`--category` narrows the operation plan and filesystem preflight targets to categories in `lookupKeys`; it does not skip whole-document YAML parsing or the configuration checks above. Categories outside `lookupKeys` cannot be selected. Every group and model entry within each selected category participates; this flag does not select one model. For example, if category `loras` has enabled group A and disabled group B, `sync --category loras` plans A's links and removal of B's listed links. For a request concerning one model, inspect the dry-run to ensure it contains no writes outside the request before executing.

Paths are literal: `~`, environment variables, and glob patterns are not expanded. `destination` and `baseDirectory` are absolute paths; `weightsList` and `ignoreList` contain relative paths without `.` or `..` components. Link names match the listed relative paths; aliases are unsupported. An enabled group's `weightsList` is kept by `sync`; its `ignoreList` is a removal candidate. A disabled group's `weightsList` is also a removal candidate. `clear` considers both lists for enabled and disabled groups. Removing an entry from configuration does not automatically remove its link; retain an entry for `clear` or mark it for removal and run `sync`.

## Safety

- Regular files and directories at link destinations are protected. Do not edit the management database (`data/links.sqlite`) directly.
- `sync` adopts an unmanaged symlink only when its current target matches the configured source. `clear` does not remove unmanaged links. Managed links changed outside the database record are treated as manual changes and rejected.
- Dry-run does not change links or the management database. A preflight error prevents either from being changed. Execution failures can leave completed operations in place; inspect their actual state before retrying.
