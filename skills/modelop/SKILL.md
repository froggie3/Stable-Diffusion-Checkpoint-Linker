---
name: modelop
description: "Plan and run SD-Linker sync or clear operations for Stable Diffusion model symlinks using an explicit YAML configuration."
---

# modelop

Use SD-Linker to manage model symlinks without copying or modifying model files. Locate a checkout containing `bin/sync`, `bin/clear`, and `vendor/autoload.php`, then identify the explicit YAML configuration for this request. The installed skill directory is not the linker checkout. Do not assume a checkout or configuration path:

```bash
LINKER_ROOT=/path/to/Stable-Diffusion-Checkpoint-Linker
CONFIG=/path/to/config.yaml
```

Read the selected command's `--help` when you need current option, configuration, or safety details. It documents stdin, category selection, path rules, operation behavior, streams, exit codes, and failure handling.

Preview the requested operation, inspect its planned targets, run the same command with the same configuration and category flags, then verify the affected links and source files:

```bash
php "$LINKER_ROOT/bin/sync" --dry-run < "$CONFIG"
php "$LINKER_ROOT/bin/sync" < "$CONFIG"
```

Use `bin/clear` for a clear request. Repeat the same `--category NAME` arguments on preview and execution. If preview fails or execution reports an error or incomplete work, stop before retrying; inspect the reported targets and current filesystem/database state first. Do not insert `clear` before a requested `sync` as a precaution, and do not use manual `ln` or `rm` commands to bypass CLI checks.
