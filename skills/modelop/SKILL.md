---
name: modelop
description: "Plan and run SD-Linker sync or clear operations for Stable Diffusion model symlinks using an explicit YAML configuration."
---

# modelop

Use SD-Linker to manage model symlinks without copying or modifying model files. Locate a checkout containing the executable `bin/linker` and `vendor/autoload.php`, then identify the explicit YAML configuration for this request. The installed skill directory is not the linker checkout. Do not assume a checkout or configuration path:

```bash
LINKER_ROOT=/path/to/Stable-Diffusion-Checkpoint-Linker
CONFIG=/path/to/config.yaml
```

Read the selected command's `--help` when you need current option, configuration, or safety details. It documents stdin, category selection, path rules, operation behavior, streams, exit codes, and failure handling.

To open a configuration for editing, run `"$LINKER_ROOT/bin/linker" edit "$CONFIG"`. The editor comes from `VISUAL`, then `EDITOR`; you can supply an explicit editor and arguments after the config path. This command does not validate YAML or run an operation. Use the command help for exact argument behavior.

Preview the requested operation, inspect its planned targets, run the same command with the same configuration and category flags, then verify the affected links and source files:

```bash
"$LINKER_ROOT/bin/linker" sync --dry-run < "$CONFIG"
"$LINKER_ROOT/bin/linker" sync < "$CONFIG"
```

Use `"$LINKER_ROOT/bin/linker" clear` for a clear request. Repeat the same `--category NAME` arguments on preview and execution. If preview fails or execution reports an error or incomplete work, stop before retrying; inspect the reported targets and current filesystem/database state first. Do not insert `clear` before a requested `sync` as a precaution, and do not use manual `ln` or `rm` commands to bypass CLI checks.
