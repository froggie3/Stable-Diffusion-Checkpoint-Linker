<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

use Iigau\StableDiffusionCheckpointLinker\Link\LinkerInterface;
use Iigau\StableDiffusionCheckpointLinker\Link\FilesystemKind;
use Iigau\StableDiffusionCheckpointLinker\Link\PathIdentity;
use Iigau\StableDiffusionCheckpointLinker\Record\LinkRecordRepository;
use RuntimeException;
use Throwable;

final class OperationExecutor
{
    public function __construct(
        private LinkerInterface $linker,
        private LinkRecordRepository $records,
        private PathIdentity $paths
    ) {
    }

    public function execute(PlannedOperation $operation): array
    {
        $entry = $this->linker->inspect($operation->target);
        $recordSource = $this->records->getSource($operation->target);
        $this->assertUnchanged($operation, $entry, $recordSource);

        $effects = [];
        try {
            if ($operation->type === OperationType::LINK) {
                clearstatcache(true, (string) $operation->source);
                if (!is_file((string) $operation->source)) {
                    throw new RuntimeException("link source disappeared or is not a file: {$operation->source}");
                }
            }
            switch ($operation->action) {
                case OperationAction::NOOP:
                    return [];
                case OperationAction::REGISTER:
                    $this->records->upsert((string) $operation->source, $operation->target);
                    return ['registered existing matching link'];
                case OperationAction::CREATE:
                case OperationAction::REPAIR:
                    $this->linker->createLink((string) $operation->source, $operation->target);
                    $effects[] = 'created link';
                    $this->records->upsert((string) $operation->source, $operation->target);
                    $effects[] = 'saved database record';
                    return $effects;
                case OperationAction::REPLACE:
                    $this->linker->removeLink($operation->target);
                    $effects[] = 'removed previous link';
                    $this->linker->createLink((string) $operation->source, $operation->target);
                    $effects[] = 'created replacement link';
                    $this->records->upsert((string) $operation->source, $operation->target);
                    $effects[] = 'updated database record';
                    return $effects;
                case OperationAction::UNLINK:
                    $this->linker->removeLink($operation->target);
                    $effects[] = 'removed link';
                    if (!$this->records->deleteRecord($operation->target)) {
                        throw new RuntimeException('database record deletion returned false.');
                    }
                    $effects[] = 'deleted database record';
                    return $effects;
                case OperationAction::DELETE_RECORD:
                    if (!$this->records->deleteRecord($operation->target)) {
                        throw new RuntimeException('database record deletion returned false.');
                    }
                    return ['deleted database record for missing link'];
                default:
                    throw new RuntimeException("unknown planned action: {$operation->action->value}");
            }
        } catch (Throwable $e) {
            throw new OperationExecutionException($e->getMessage(), $effects, $e);
        }
    }

    private function assertUnchanged(PlannedOperation $operation, \Iigau\StableDiffusionCheckpointLinker\Link\FilesystemEntry $entry, ?string $recordSource): void
    {
        clearstatcache(true);
        $snapshot = $operation->snapshot;
        if ($entry->kind !== $snapshot->kind || $recordSource !== $snapshot->recordSource) {
            throw new OperationExecutionException("state changed after preflight: {$operation->target}");
        }
        if ($entry->kind === FilesystemKind::SYMLINK) {
            $current = $entry->linkTarget === null
                ? null
                : $this->paths->identity($entry->linkTarget, dirname($operation->target));
            $planned = $snapshot->linkTarget === null
                ? null
                : $this->paths->identity($snapshot->linkTarget, dirname($operation->target));
            if ($current !== $planned) {
                throw new OperationExecutionException("symlink changed after preflight: {$operation->target}");
            }
        }

        $parentPath = dirname($operation->target);
        clearstatcache(true, $parentPath);
        $parentCanonical = realpath($parentPath);
        $parentStat = $parentCanonical === false ? false : stat($parentPath);
        $parentIsDirectory = is_dir($parentPath);
        $parentSnapshot = $snapshot->parent;
        if (($parentCanonical === false ? null : $parentCanonical) !== $parentSnapshot->canonical
            || ($parentStat === false ? null : $parentStat['dev']) !== $parentSnapshot->device
            || ($parentStat === false ? null : $parentStat['ino']) !== $parentSnapshot->inode
            || $parentIsDirectory !== $parentSnapshot->isDirectory) {
            throw new OperationExecutionException("target parent changed after preflight: $parentPath");
        }

        $rootCanonical = realpath($snapshot->rootPath);
        if (($rootCanonical === false ? null : $rootCanonical) !== $snapshot->rootCanonical) {
            throw new OperationExecutionException("destination directory changed after preflight: {$snapshot->rootPath}");
        }
        if ($parentCanonical !== false && $rootCanonical !== false
            && $parentCanonical !== $rootCanonical
            && !str_starts_with($parentCanonical, rtrim($rootCanonical, '/') . '/')) {
            throw new OperationExecutionException("target parent now resolves outside its destination directory: $parentPath");
        }
    }
}
