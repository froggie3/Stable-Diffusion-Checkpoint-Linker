<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

use Iigau\StableDiffusionCheckpointLinker\Config\CategoryConfiguration;
use Iigau\StableDiffusionCheckpointLinker\Link\FilesystemKind;
use Iigau\StableDiffusionCheckpointLinker\Link\FilesystemEntry;
use Iigau\StableDiffusionCheckpointLinker\Link\LinkerInterface;
use Iigau\StableDiffusionCheckpointLinker\Link\PathIdentity;
use Iigau\StableDiffusionCheckpointLinker\Record\LinkRecordRepository;

final class OperationPlanner
{
    /** @param array<string, CategoryConfiguration> $categories @param list<string> $lookupKeys */
    public function __construct(
        private array $categories,
        private array $lookupKeys,
        private LinkerInterface $linker,
        private LinkRecordRepository $records,
        private PathIdentity $paths
    ) {
    }

    public function planSync(): OperationPlan
    {
        $desired = [];
        foreach ($this->groups() as [$category, $group]) {
            $root = $category->destination;
            if ($group->enabled) {
                foreach ($group->weightsList as $weight) {
                    $desired[] = [OperationType::LINK, $group->baseDirectory . '/' . $weight, $root . '/' . $weight, $root];
                }
                foreach ($group->ignoreList as $weight) {
                    $desired[] = [OperationType::UNLINK, null, $root . '/' . $weight, $root];
                }
            } else {
                foreach ($group->weightsList as $weight) {
                    $desired[] = [OperationType::UNLINK, null, $root . '/' . $weight, $root];
                }
            }
        }
        return $this->buildPlan($desired);
    }

    public function planClear(): OperationPlan
    {
        $desired = [];
        foreach ($this->groups() as [$category, $group]) {
            foreach ([...$group->weightsList, ...$group->ignoreList] as $weight) {
                $root = $category->destination;
                $desired[] = [OperationType::UNLINK, null, $root . '/' . $weight, $root];
            }
        }
        return $this->buildPlan($desired);
    }

    private function groups(): iterable
    {
        foreach ($this->lookupKeys as $key) {
            $category = $this->categories[$key];
            foreach ($category->groups as $group) {
                yield [$category, $group];
            }
        }
    }

    private function buildPlan(array $desired): OperationPlan
    {
        $plan = new OperationPlan();
        $seen = [];
        foreach ($desired as [$type, $source, $target, $root]) {
            $targetKey = $this->paths->entryIdentity($target);
            if (isset($seen[$targetKey])) {
                [$previousType, $previousSource] = $seen[$targetKey];
                if ($previousType === $type && $previousSource === $source) {
                    continue;
                }
                $plan->addError("conflicting operations target the same path: $target");
                continue;
            }
            $seen[$targetKey] = [$type, $source];

            if ($type === OperationType::LINK) {
                if ($source === null || !is_file($source)) {
                    $plan->addError("link source is missing or is not a file: $source");
                    continue;
                }
                if ($this->paths->entryIdentity($source) === $targetKey) {
                    $plan->addError("link source and target resolve to the same path: $target");
                    continue;
                }
            }

            $entry = $this->linker->inspect($target);
            $recordSource = $this->records->getSource($target);
            $action = $this->resolve($type, $source, $target, $entry, $recordSource, $plan);
            if ($action === null) {
                continue;
            }
            $parent = $this->directorySnapshot(dirname($target));
            $rootCanonical = realpath($root) ?: null;
            if ($type === OperationType::LINK) {
                if ($rootCanonical === null || !is_dir($root) || $parent->canonical === null || !$parent->isDirectory) {
                    $plan->addError('destination parent is missing or is not a directory: ' . dirname($target));
                    continue;
                }
                if (!$this->isWithin($parent->canonical, $rootCanonical)) {
                    $plan->addError("target parent resolves outside its destination directory: $target");
                    continue;
                }
            }
            if ($entry->exists() && ($parent->canonical === null || ($rootCanonical !== null && !$this->isWithin($parent->canonical, $rootCanonical)))) {
                $plan->addError("existing target resolves outside its destination directory: $target");
                continue;
            }
            if ($parent->canonical !== null && $rootCanonical !== null && !$this->isWithin($parent->canonical, $rootCanonical)) {
                $plan->addError("target parent resolves outside its destination directory: $target");
                continue;
            }
            $snapshot = new OperationSnapshot($entry->kind, $entry->linkTarget, $recordSource, $parent, $rootCanonical, $root);
            $plan->add(new PlannedOperation($type, $source, $target, $action, $snapshot));
        }
        return $plan;
    }

    private function resolve(OperationType $type, ?string $source, string $target, FilesystemEntry $entry, ?string $recordSource, OperationPlan $plan): ?OperationAction
    {
        if ($type === OperationType::LINK) {
            if (in_array($entry->kind, [FilesystemKind::FILE, FilesystemKind::DIRECTORY, FilesystemKind::OTHER], true)) {
                $plan->addError("target is occupied by a non-symlink filesystem entry: $target");
                return null;
            }
            if ($entry->kind === FilesystemKind::ABSENT) {
                return $recordSource === null ? OperationAction::CREATE : OperationAction::REPAIR;
            }
            $actualSource = $entry->linkTarget === null ? null : $this->paths->identity($entry->linkTarget, dirname($target));
            $desiredSource = $this->paths->identity($source);
            if ($recordSource === null) {
                if ($actualSource === $desiredSource) {
                    return OperationAction::REGISTER;
                }
                $plan->addError("unmanaged symlink conflicts with configured source: $target");
                return null;
            }
            $recordIdentity = $this->paths->identity($recordSource);
            if ($actualSource !== $recordIdentity) {
                $plan->addError("managed symlink differs from its database record: $target");
                return null;
            }
            return $recordIdentity === $desiredSource ? OperationAction::NOOP : OperationAction::REPLACE;
        }

        if (in_array($entry->kind, [FilesystemKind::FILE, FilesystemKind::DIRECTORY, FilesystemKind::OTHER], true)) {
            $plan->addError("refusing to remove a non-symlink filesystem entry: $target");
            return null;
        }
        if ($entry->kind === FilesystemKind::ABSENT) {
            return $recordSource === null ? OperationAction::NOOP : OperationAction::DELETE_RECORD;
        }
        if ($recordSource === null) {
            $plan->addError("cannot clear an unmanaged symlink: $target");
            return null;
        }
        $actualSource = $entry->linkTarget === null ? null : $this->paths->identity($entry->linkTarget, dirname($target));
        if ($actualSource !== $this->paths->identity($recordSource)) {
            $plan->addError("managed symlink differs from its database record: $target");
            return null;
        }
        return OperationAction::UNLINK;
    }

    private function directorySnapshot(string $directory): DirectorySnapshot
    {
        clearstatcache(true, $directory);
        $canonical = realpath($directory);
        $stat = $canonical === false ? false : stat($directory);
        return new DirectorySnapshot(
            $canonical === false ? null : $canonical,
            $stat === false ? null : $stat['dev'],
            $stat === false ? null : $stat['ino'],
            is_dir($directory)
        );
    }

    private function isWithin(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, rtrim($root, '/') . '/');
    }
}
