<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Record;

use PDO;
use RuntimeException;

final class SQLiteRecordRepository implements LinkRecordRepository
{
    private ?PDO $pdo = null;
    /** @var resource|null */
    private $lockHandle = null;

    public function __construct(private string $dbPath)
    {
        if (is_file($dbPath)) {
            $this->pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }
    }

    public function hasRecord(string $target): bool
    {
        return $this->getSource($target) !== null;
    }

    public function getSource(string $target): ?string
    {
        if ($this->pdo === null) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT source FROM links WHERE target = :target');
        $stmt->execute([':target' => $target]);
        $source = $stmt->fetchColumn();
        return $source === false ? null : (string) $source;
    }

    public function upsert(string $source, string $target): void
    {
        $pdo = $this->writablePdo();
        $stmt = $pdo->prepare(<<<'SQL'
INSERT INTO links (source, target, created_at)
VALUES (:source, :target, :ts)
ON CONFLICT(target) DO UPDATE SET source = excluded.source, created_at = excluded.created_at
SQL);
        $stmt->execute([
            ':source' => $source,
            ':target' => $target,
            ':ts' => (new \DateTimeImmutable())->format('c'),
        ]);
    }

    public function deleteRecord(string $target): bool
    {
        $stmt = $this->writablePdo()->prepare('DELETE FROM links WHERE target = :target');
        return $stmt->execute([':target' => $target]);
    }

    public function activateForWrites(): void
    {
        if ($this->lockHandle !== null) {
            return;
        }
        $directory = dirname($this->dbPath);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Could not create database directory: $directory");
        }
        $lock = @fopen($this->dbPath . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Another linker process is using this database.');
        }
        $this->lockHandle = $lock;

        $this->pdo = new PDO('sqlite:' . $this->dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS links (
    source TEXT NOT NULL,
    target TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    PRIMARY KEY(source, target)
)
SQL);
    }

    private function writablePdo(): PDO
    {
        if ($this->lockHandle === null || $this->pdo === null) {
            throw new RuntimeException('Database writes have not been activated.');
        }
        return $this->pdo;
    }

    public function __destruct()
    {
        if (is_resource($this->lockHandle)) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
        }
    }
}
