<?php

namespace Awirhosein\QueueSystem\Drivers;

use Awirhosein\QueueSystem\Contracts\QueueContract;
use PDO;
use Ramsey\Uuid\Uuid;

class DatabaseDriver implements QueueContract
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite:' . __DIR__ . '/../../database/queue.sqlite');
        $this->createTables();
    }

    private function createTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL UNIQUE,
                queue TEXT NULL,
                payload TEXT NOT NULL,
                priority INTEGER DEFAULT 0,
                attempts INTEGER NOT NULL DEFAULT 0,
                reserved_at INTEGER NULL,
                available_at INTEGER NULL,
                created_at INTEGER NOT NULL
            );
            
             CREATE TABLE IF NOT EXISTS failed_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL UNIQUE,
                queue TEXT NULL,
                payload TEXT NOT NULL,
                message TEXT NOT NULL,
                created_at INTEGER NOT NULL
            );
        ");
    }

    public function push($job, ?int $availableAt = null, ?string $queue = null, ?int $priority = 0): void
    {
        $this->pdo
            ->prepare("
                INSERT INTO jobs (uuid, queue, payload, priority, available_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ")
            ->execute([
                Uuid::uuid4()->toString(),
                $queue,
                serialize($job),
                $priority,
                $availableAt,
                $this->now(),
            ]);
    }

    public function next(?string $queue = null): ?array
    {
        $this->pdo->exec('BEGIN IMMEDIATE TRANSACTION');

        $query = "
            SELECT * FROM jobs
            WHERE reserved_at IS NULL AND (available_at IS NULL OR available_at <= ?)
        ";

        $values[] = $this->now();

        if ($queue) {
            $query .= " AND queue = ?";
            $values[] = $queue;
        }

        $query .= " ORDER BY priority DESC LIMIT 1";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($values);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($job) {
            $job['payload'] = unserialize($job['payload']);
            $this->reserve($job['uuid']);

            $this->pdo->exec('COMMIT');

            return $job;
        }

        $this->pdo->exec('COMMIT');

        return null;
    }

    protected function reserve(string $uuid): void
    {
        $this->pdo
            ->prepare("UPDATE jobs SET reserved_at = ? WHERE uuid = ?")
            ->execute([$this->now(), $uuid]);
    }

    public function attempt(array $job): ?array
    {
        $job['attempts'] += 1;

        $this->pdo
            ->prepare("UPDATE jobs SET attempts = ? WHERE uuid = ?")
            ->execute([$job['attempts'], $job['uuid']]);

        return $job;
    }

    public function markAsFailed(array $job, string $message): void
    {
        $this->pdo
            ->prepare("
                INSERT INTO failed_jobs (uuid, queue, payload, message, created_at)
                VALUES (?, ?, ?, ?, ?)
            ")
            ->execute([
                $job['uuid'],
                $job['queue'],
                serialize($job['payload']),
                $message,
                $this->now(),
            ]);
    }

    public function remove(string $uuid): void
    {
        $this->pdo
            ->prepare("DELETE FROM jobs WHERE uuid = ?")
            ->execute([$uuid]);
    }

    public function retry(string $uuid): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM failed_jobs WHERE uuid = ?");
        $stmt->execute([$uuid]);
        $failed_job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $failed_job) {
            return;
        }

        // add to jobs
        $this->push(unserialize($failed_job['payload']), queue: $failed_job['queue']);

        // delete from failed_jobs
        $this->pdo
            ->prepare("DELETE FROM failed_jobs WHERE uuid = ?")
            ->execute([$uuid]);
    }

    public function isEmpty(?string $queue = null): bool
    {
        $query = "SELECT COUNT(*) as count FROM jobs WHERE reserved_at IS NULL";
        $values = [];

        if ($queue) {
            $query .= " AND queue = ?";
            $values[] = $queue;
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($values);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data['count'] === 0;
    }

    protected function now(): int
    {
        return time();
    }

    public function jobs(): array
    {
        return $this->pdo
            ->query("SELECT * FROM jobs")
            ->fetchAll();
    }

    public function failedJobs(): array
    {
        return $this->pdo
            ->query("SELECT * FROM failed_jobs")
            ->fetchAll();
    }
}