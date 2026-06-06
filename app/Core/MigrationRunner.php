<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function migrate(string $path): void
    {
        $this->createMigrationsTable();

        foreach ($this->migrationFiles($path) as $file) {
            $migration = basename($file, '.php');

            if ($this->hasRun($migration)) {
                continue;
            }

            $definition = require $file;
            $this->runStatements($definition['up'] ?? []);
            $this->record($migration);
        }
    }

    public function rollbackLast(string $path): void
    {
        $this->createMigrationsTable();

        $migration = $this->lastMigration();

        if ($migration === null) {
            return;
        }

        $file = rtrim($path, '/') . '/' . $migration . '.php';

        if (! is_file($file)) {
            throw new RuntimeException("Migration dosyası bulunamadı: {$migration}");
        }

        $definition = require $file;
        $statements = array_reverse($definition['down'] ?? []);

        if ($statements === []) {
            throw new RuntimeException("Migration için down SQL bulunamadı: {$migration}");
        }

        $this->runStatements($statements);
        $this->removeRecord($migration);
    }

    private function createMigrationsTable(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function migrationFiles(string $path): array
    {
        $files = glob(rtrim($path, '/') . '/*.php') ?: [];
        sort($files);

        return $files;
    }

    private function hasRun(string $migration): bool
    {
        $statement = $this->database->prepare('SELECT COUNT(*) FROM migrations WHERE migration = :migration');
        $statement->execute(['migration' => $migration]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function runStatements(array $statements): void
    {
        if ($statements === []) {
            throw new RuntimeException('Migration içinde çalıştırılacak SQL bulunamadı.');
        }

        foreach ($statements as $statement) {
            $this->database->exec($statement);
        }
    }

    private function record(string $migration): void
    {
        $statement = $this->database->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
        $statement->execute(['migration' => $migration]);
    }

    private function removeRecord(string $migration): void
    {
        $statement = $this->database->prepare('DELETE FROM migrations WHERE migration = :migration');
        $statement->execute(['migration' => $migration]);
    }

    private function lastMigration(): ?string
    {
        $statement = $this->database->query('SELECT migration FROM migrations ORDER BY id DESC LIMIT 1');
        $migration = $statement->fetchColumn();

        return $migration === false ? null : (string) $migration;
    }
}
