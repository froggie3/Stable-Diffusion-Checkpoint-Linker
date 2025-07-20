<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

// ==== Counter ====
final class Counter
{
    private array $counts = [];

    public function increment(string $key): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }

    public function get(string $key): int
    {
        return $this->counts[$key] ?? 0;
    }
}

// ==== SyncState interface ====
interface SyncState
{
    public function shouldSync(): bool;
    public function sync(): void;
}

// ==== Concrete States ====
final class AlreadyLinkedState implements SyncState
{
    public function __construct(private Logger $logger, private string $source, private string $target, private Counter $counter) {}

    public function shouldSync(): bool
    {
        $this->logger->debug("already linked", ['source' => $this->source, 'target' => $this->target]);
        $this->counter->increment('alreadyLinked');
        return false;
    }

    public function sync(): void {}
}

final class SourceNotFoundState implements SyncState
{
    public function __construct(private Logger $logger, private string $source, private Counter $counter) {}

    public function shouldSync(): bool
    {
        $this->logger->error("source not found", ['source' => $this->source]);
        $this->counter->increment('error');
        return false;
    }

    public function sync(): void {}
}

class RecordExistsWithoutLinkState implements SyncState
{
    public function __construct(
        private Logger $logger,
        private Linker $linker,
        private string $source,
        private string $target,
        private Counter $counter
    ) {}

    public function shouldSync(): bool
    {
        $this->logger->debug("record exists but symlink missing", ['source' => $this->source, 'target' => $this->target]);
        $this->counter->increment('inconsistent');
        return true;
    }

    public function sync(): void
    {
        if ($this->linker->link($this->source, $this->target) === LinkResult::ACCEPT) {
            $this->logger->debug("link created", ['source' => $this->source, 'target' => $this->target]);
            $recordResult = $this->linker->createRecord($this->source, $this->target);
            if ($recordResult === RecordResult::NEWLY_LINKED) {
                $this->logger->debug("successfully linked", ['source' => $this->source, 'target' => $this->target]);
                $this->counter->increment('newlyLinked');
                $this->counter->increment('alreadyLinked');
            } else {
                $this->logger->error("record creation failed", ['source' => $this->source, 'target' => $this->target]);
                $this->counter->increment('error');
            }
        } else {
            $this->logger->error("symlink creation failed", ['source' => $this->source, 'target' => $this->target]);
            $this->counter->increment('error');
        }
    }
}

final class TargetExistsWithoutRecordState implements SyncState
{
    public function __construct(private Logger $logger, private string $source, private string $target, private Counter $counter, private Unlinker $unlinker) {}

    public function shouldSync(): bool
    {
        $this->logger->warning("symlink exists but record missing", ['source' => $this->source, 'target' => $this->target]);
        $this->counter->increment('inconsistent');
        return true;
    }

    public function sync(): void
    {
        if ($this->unlinker->unlink($this->target)) {
            $this->logger->debug("unlinked orphan", ['target']);
            $this->counter->increment('removed');
        } else {
            $this->logger->error("unlink failed", ['target']);
            $this->counter->increment('error');
        }
    }
}

final class ReadyToLinkState extends RecordExistsWithoutLinkState {}

final class UnknownErrorState implements SyncState
{
    public function __construct(private Logger $logger, private string $source, private string $target, private Counter $counter) {}

    public function shouldSync(): bool
    {
        $this->logger->error("unclassified error", ['source' => $this->source, 'target' => $this->target]);
        $this->counter->increment('error');
        return false;
    }

    public function sync(): void {}
}

// ==== Factory ====
final class SyncStateFactory
{
    public function __construct(
        private Logger $logger,
        private Linker $linker,
        private Unlinker $unlinker,
        private Counter $counter
    ) {}

    public function create(SyncObjectStatus $status, string $source, string $target): SyncState
    {
        return match ($status) {
            SyncObjectStatus::ALREADY_LINKED => new AlreadyLinkedState($this->logger, $source, $target, $this->counter),
            SyncObjectStatus::SOURCE_NOT_FOUND => new SourceNotFoundState($this->logger, $source, $this->counter),
            SyncObjectStatus::RECORD_EXISTS_WITHOUT_LINK => new RecordExistsWithoutLinkState($this->logger, $this->linker, $source, $target, $this->counter),
            SyncObjectStatus::TARGET_ALREADY_EXISTS_WITHOUT_RECORD => new TargetExistsWithoutRecordState($this->logger, $source, $target, $this->counter, $this->unlinker),
            SyncObjectStatus::READY => new ReadyToLinkState($this->logger, $this->linker, $source, $target, $this->counter),
            default => new UnknownErrorState($this->logger, $source, $target, $this->counter),
        };
    }
}

// ==== Syncronizer (updated) ====
final class Syncronizer
{
    public function __construct(
        private Logger $logger,
        private Procedures $procedures,
        private Linker $linker,
        private Unlinker $unlinker
    ) {}

    public function run(): SyncronizerResult
    {
        $counter = new Counter();
        $factory = new SyncStateFactory($this->logger, $this->linker, $this->unlinker, $counter);

        foreach ($this->procedures->link as list($source, $target)) {
            $status = $this->linker->test($source, $target);
            $state = $factory->create($status, $source, $target);

            if (!$state->shouldSync()) {
                continue;
            }

            $state->sync();
        }

        foreach ($this->procedures->unlink as $path) {
            if ($this->unlinker->unlink($path)) {
                $counter->increment('removed');
            }
        }

        return new SyncronizerResult(
            $counter->get('newlyLinked'),
            $counter->get('error'),
            $counter->get('removed'),
            $counter->get('alreadyLinked'),
            count($this->procedures->unlink),
            $counter->get('inconsistent')
        );
    }
}
