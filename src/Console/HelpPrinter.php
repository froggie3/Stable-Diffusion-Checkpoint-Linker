<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Console;

final class HelpPrinter
{
    public function printListHelp(): void
    {
        $output = new ConsoleOutput();
        $output->line('Usage: linker list [--help]');
        $output->line('List YAML files recursively under configuration_dir, one absolute path per line, sorted.');
        $output->line('Directory symlinks are not followed. No YAML is parsed and no database is opened.');
        $output->line('Exit codes: 0 success, 1 settings or directory failure, 2 invalid arguments.');
    }

    public function printGlobalHelp(): void
    {
        $output = new ConsoleOutput();
        foreach ([
            'Usage: linker <command> [OPTIONS] [CONFIG]',
            '',
            'Commands:',
            '  sync     ' . Command::SYNC->description(),
            '  clear    ' . Command::CLEAR->description(),
            '  edit     ' . Command::EDIT->description(),
            '  list     ' . Command::LIST->description(),
            '',
            'Run linker <command> --help for configuration, options, safety, and output details.',
            'For sync and clear, configuration is read from CONFIG or standard input (< config.yaml). Help does not read input or open the database.',
        ] as $line) {
            $output->line($line);
        }
    }

    public function printEditHelp(): void
    {
        $output = new ConsoleOutput();
        foreach ([
            'Usage: linker edit CONFIG [EDITOR [ARGS...]]',
            '',
            'Open CONFIG in an editor. This command does not read or validate YAML, access the database, or run sync.',
            'If EDITOR is omitted, VISUAL is used, then EDITOR. The selected environment value is a shell command; CONFIG is passed as one final argument.',
            'An explicit EDITOR and its ARGS are executed directly without a shell, and CONFIG is appended as the final argument.',
            'Standard input, output, error, and the current working directory are inherited. The command waits for the editor.',
            'Exit codes: 0 editor success, 1 editor failure, 2 invalid arguments.',
        ] as $line) {
            $output->line($line);
        }
    }

    public function printHelp(Command $command): void
    {
        $output = new ConsoleOutput();
        $modeAction = $command === Command::CLEAR
            ? 'Remove only links that the management database confirms are managed and that this configuration lists.'
            : 'Create, repair, replace, register, or remove the links selected by this configuration.';
        $lines = [
            "Usage: linker {$command->value} [OPTIONS] [CONFIG]",
            '',
            $modeAction,
            'Configuration is read from CONFIG when provided, otherwise from standard input (< config.yaml). --help does not read input or open the database.',
            '',
            'Options:',
            '  -h, --help                 show this help.',
            '  --dry-run                  validate and print planned actions; make no changes.',
            '  -v, --verbose              print successful operation details and counts to stdout.',
            '  --category NAME            limit to a lookupKeys category (repeatable; --category=NAME is also accepted).',
            '',
            'Examples:',
            "  linker {$command->value} --help",
            "  linker {$command->value} --dry-run < config.yaml",
            "  linker {$command->value} -v --category checkpoint < config.yaml",
            '',
            'Minimal YAML configuration:',
            '  lookupKeys: [checkpoint]',
            '  source:',
            '    checkpoint: []',
            '  destination:',
            '    checkpoint: /srv/models/checkpoint',
            '',
            'Configuration keys and types:',
            '  lookupKeys: required non-empty list of unique category strings; controls category order.',
            '  source: required mapping; each listed category maps to a list of model-group mappings (empty allowed).',
            '  destination: required mapping; each listed category maps to a safe absolute path.',
            '  source.<category>.<index>.meta.enabled: required boolean.',
            '  source.<category>.<index>.baseDirectory: required safe absolute path.',
            '  source.<category>.<index>.weightsList: required list of relative path strings; empty allowed.',
            '  source.<category>.<index>.ignoreList: optional list of relative path strings; defaults to empty.',
            '  All categories in lookupKeys are schema-validated, including categories outside --category.',
            '  --category only chooses planned categories and preserves lookupKeys order.',
            '',
            'Selection behavior:',
            '  Enabled groups: sync keeps weightsList links and treats ignoreList as removal candidates.',
            '  Disabled groups: sync treats weightsList as removal candidates; ignoreList has no sync effect.',
            '  clear considers both weightsList and ignoreList for enabled and disabled groups.',
            '  Entries removed from YAML are not automatically pruned.',
            '',
            'Paths and safety:',
            '  Paths are literal; no tilde, environment-variable, or glob expansion occurs.',
            '  baseDirectory and destination paths must be absolute; list entries must be relative and contain no empty, dot, or .. components.',
            '  Regular files/directories are protected. Sync can register an unmanaged symlink only when it already points to the configured source; other unmanaged links and manually changed managed links are rejected.',
            '  Preflight validates the full selected plan before any changes. Preflight failure makes no changes.',
            '  Execution stops at the first failure; completed work remains and is not rolled back.',
            '  Failure diagnostics identify completed operations, partial effects, the failed target, and operations not run.',
            '',
            'Output and status:',
            '  Successful execution is silent by default; verbose success details and counts go to stdout.',
            '  Errors and failure reports go to stderr. Dry-run plans and help always go to stdout.',
            '  Dry-run actions: create makes a missing link; repair recreates a missing managed link; replace redirects a managed link; register records a matching unmanaged link; unlink removes a managed link and record; delete_record removes a stale record for a missing link; noop leaves a matching target unchanged.',
            '  Verbose counts are created, repaired, replaced, registered, removed, unchanged, and operations completed. Removed includes unlink and delete_record actions.',
            '  Exit codes: 0 success, 1 operation/configuration failure, 2 invalid arguments.',
            '  Managed-link records use database_path in setting.ini (default: data/links.sqlite under the project checkout).',
        ];
        foreach ($lines as $line) {
            $output->line($line);
        }
    }
}
