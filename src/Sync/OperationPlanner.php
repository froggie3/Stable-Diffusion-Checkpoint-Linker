<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Sync;

use Iigau\StableDiffusionCheckpointLinker\Link\LinkerInterface;
use Iigau\StableDiffusionCheckpointLinker\Link\PathIdentity;
use Iigau\StableDiffusionCheckpointLinker\Record\LinkRecordRepository;

final class OperationPlanner
{
    public function __construct(
        private array $lookupKeys,
        private array $source,
        private array $destination,
        private LinkerInterface $linker,
        private LinkRecordRepository $records,
        private PathIdentity $paths
    ) {}

    public function planSync(): OperationPlan
    {
        $desired = [];
        foreach ($this->groups() as [$category, $group]) {
            $targetDirectory = $this->destination[$category];
            if ($group['meta']['enabled']) {
                foreach ($group['weightsList'] as $weight) {
                    $desired[] = ['link', $group['baseDirectory'] . '/' . $weight, $targetDirectory . '/' . $weight, $targetDirectory];
                }
                foreach ($group['ignoreList'] ?? [] as $weight) {
                    $desired[] = ['unlink', null, $targetDirectory . '/' . $weight, $targetDirectory];
                }
            } else {
                foreach ($group['weightsList'] as $weight) {
                    $desired[] = ['unlink', null, $targetDirectory . '/' . $weight, $targetDirectory];
                }
            }
        }
        return $this->buildPlan($desired);
    }

    public function planClear(): OperationPlan
    {
        $desired = [];
        foreach ($this->groups() as [$category, $group]) {
            foreach ([...$group['weightsList'], ...($group['ignoreList'] ?? [])] as $weight) {
                $desired[] = ['unlink', null, $this->destination[$category] . '/' . $weight, $this->destination[$category]];
            }
        }
        return $this->buildPlan($desired);
    }

    private function groups(): iterable
    {
        foreach ($this->lookupKeys as $category) {
            foreach ($this->source[$category] as $group) {
                yield [$category, $group];
            }
        }
    }

    private function buildPlan(array $desired): OperationPlan
    {
        $plan = new OperationPlan();
        $seen = [];
        foreach ($desired as [$type, $source, $target, $root]) {
            $targetKey = $this->targetIdentity($target);
            if (isset($seen[$targetKey])) {
                [$previousType, $previousSource] = $seen[$targetKey];
                if ($previousType === $type && $previousSource === $source) {
                    continue;
                }
                $plan->addError("Conflicting operations target the same path: $target");
                continue;
            }
            $seen[$targetKey] = [$type, $source];

            if ($type === 'link') {
                if ($source === null || !is_file($source)) {
                    $plan->addError("Link source is missing or is not a file: $source");
                    continue;
                }
                if ($this->paths->entryIdentity($source) === $targetKey) {
                    $plan->addError("Link source and target resolve to the same path: $target");
                    continue;
                }
            }

            $entry = $this->linker->inspect($target);
            $recordSource = $this->records->getSource($target);
            $action = $this->resolve($type, $source, $target, $entry, $recordSource, $plan);
            if ($action !== null) {
                $parent = $this->directorySnapshot(dirname($target));
                $rootCanonical = realpath($root) ?: null;
                if ($type === 'link') {
                    if ($rootCanonical === null || !is_dir($root) || $parent['canonical'] === null || !$parent['isDirectory']) {
                        $plan->addError("Destination parent is missing or is not a directory: " . dirname($target));
                        continue;
                    }
                    if (!$this->isWithin($parent['canonical'], $rootCanonical)) {
                        $plan->addError("Target parent resolves outside its destination directory: $target");
                        continue;
                    }
                }
                if ($entry->exists() && ($parent['canonical'] === null || ($rootCanonical !== null && !$this->isWithin($parent['canonical'], $rootCanonical)))) {
                    $plan->addError("Existing target resolves outside its destination directory: $target");
                    continue;
                }
                if ($parent['canonical'] !== null && $rootCanonical !== null && !$this->isWithin($parent['canonical'], $rootCanonical)) {
                    $plan->addError("Target parent resolves outside its destination directory: $target");
                    continue;
                }
                $plan->add(new PlannedOperation($type, $source, $target, $action, [
                    'kind' => $entry->kind,
                    'linkTarget' => $entry->linkTarget,
                    'recordSource' => $recordSource,
                    'parent' => $parent,
                    'rootCanonical' => $rootCanonical,
                    'rootPath' => $root,
                ]));
            }
        }
        return $plan;
    }

    private function resolve(string $type, ?string $source, string $target, object $entry, ?string $recordSource, OperationPlan $plan): ?string
    {
        if ($type === 'link') {
            if ($entry->kind === 'file' || $entry->kind === 'directory' || $entry->kind === 'other') {
                $plan->addError("Target is occupied by a non-symlink filesystem entry: $target");
                return null;
            }
            if ($entry->kind === 'absent') {
                return $recordSource === null ? 'create' : 'repair';
            }

            $actualSource = $entry->linkTarget === null
                ? null
                : $this->paths->identity($entry->linkTarget, dirname($target));
            $desiredSource = $this->paths->identity($source);
            if ($recordSource === null) {
                if ($actualSource === $desiredSource) {
                    return 'register';
                }
                $plan->addError("Unmanaged symlink conflicts with configured source: $target");
                return null;
            }
            $recordIdentity = $this->paths->identity($recordSource);
            if ($actualSource !== $recordIdentity) {
                $plan->addError("Managed symlink differs from its database record: $target");
                return null;
            }
            return $recordIdentity === $desiredSource ? 'noop' : 'replace';
        }

        if ($entry->kind === 'file' || $entry->kind === 'directory' || $entry->kind === 'other') {
            $plan->addError("Refusing to remove a non-symlink filesystem entry: $target");
            return null;
        }
        if ($entry->kind === 'absent') {
            return $recordSource === null ? 'noop' : 'delete_record';
        }
        if ($recordSource === null) {
            $plan->addError("Cannot clear an unmanaged symlink: $target");
            return null;
        }
        $actualSource = $entry->linkTarget === null
            ? null
            : $this->paths->identity($entry->linkTarget, dirname($target));
        if ($actualSource !== $this->paths->identity($recordSource)) {
            $plan->addError("Managed symlink differs from its database record: $target");
            return null;
        }
        return 'unlink';
    }

    private function directorySnapshot(string $directory): array
    {
        clearstatcache(true, $directory);
        $canonical = realpath($directory);
        $stat = $canonical === false ? false : stat($directory);
        return [
            'canonical' => $canonical === false ? null : $canonical,
            'device' => $stat === false ? null : $stat['dev'],
            'inode' => $stat === false ? null : $stat['ino'],
            'isDirectory' => is_dir($directory),
        ];
    }

    private function targetIdentity(string $target): string
    {
        return $this->paths->entryIdentity($target);
    }

    private function isWithin(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, rtrim($root, '/') . '/');
    }
}
