<?php

namespace Awirhosein\QueueSystem;

use PDO;
use Ramsey\Uuid\Uuid;

class DatabaseDriver implements QueueContract
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite:' . __DIR__ . '/../database/queue.sqlite');
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
                attempts INTEGER NOT NULL DEFAULT 0,
                available_at INTEGER NULL,
                priority INTEGER DEFAULT 0,
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
        $query = "INSERT INTO jobs (uuid, queue, payload, available_at, priority, created_at) VALUES (?, ?, ?, ?, ?, ?)";
        $values = [
            Uuid::uuid4()->toString(),
            $queue,
            serialize($job),
            $availableAt,
            $priority,
            time(),
        ];

        $this->pdo->prepare($query)->execute($values);
    }

    public function markAsFailed(array $job, string $message): void
    {
        $query = "INSERT INTO failed_jobs (uuid, queue, payload, message, created_at) VALUES (?, ?, ?, ?, ?)";
        $values = [
            $job['uuid'],
            $job['queue'],
            serialize($job['payload']),
            $message,
            time(),
        ];

        $this->pdo->prepare($query)->execute($values);
    }

    public function next(?string $queue = null): ?array
    {
        $query = "SELECT * FROM jobs WHERE (available_at IS NULL OR available_at <= ?)";
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
            return $job;
        }

        return null;
    }

    public function retry(string $uuid): void
    {
        $query = "SELECT * FROM failed_jobs WHERE uuid = ?";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$uuid]);
        $failed_job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $failed_job) {
            return;
        }

        // add to jobs
        $this->push(unserialize($failed_job['payload']), queue: $failed_job['queue']);

        // delete from failed_jobs
        $query = "DELETE FROM failed_jobs WHERE uuid = ?";
        $this->pdo->prepare($query)->execute([$uuid]);
    }

    public function isEmpty(?string $queue = null): bool
    {
        $query = "SELECT COUNT(*) as count FROM jobs";
        $values = [];

        if ($queue) {
            $query .= " WHERE queue = ?";
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

    public function attempt(array $job): ?array
    {
        $job['attempts'] += 1;

        $query = "UPDATE jobs SET attempts = ? WHERE uuid = ?";
        $values = [$job['attempts'], $job['uuid']];

        $this->pdo->prepare($query)->execute($values);

        return $job;
    }

    public function remove(string $uuid): void
    {
        $this->pdo
            ->prepare("DELETE FROM jobs WHERE uuid = ?")
            ->execute([$uuid]);
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