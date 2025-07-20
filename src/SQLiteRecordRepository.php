<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use PDO;

final class SQLiteRecordRepository implements LinkRecordRepository
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = new PDO("sqlite:$dbPath");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $this->pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS links (
    source TEXT NOT NULL,
    target TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    PRIMARY KEY(source, target)
)
SQL);
    }

    public function hasRecord(string $target): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM links WHERE target = :target');
        $stmt->execute([':target' => $target]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public function createRecord(string $source, string $target): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO links (source, target, created_at) VALUES (:source, :target, :ts)'
        );
        return $stmt->execute([
            ':source' => $source,
            ':target' => $target,
            ':ts'     => (new \DateTimeImmutable())->format('c'),
        ]);
    }

    public function deleteRecord(string $target): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM links WHERE target = :target');
        return $stmt->execute([':target' => $target]);
    }
}
