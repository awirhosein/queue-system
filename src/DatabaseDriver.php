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
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                available_at INTEGER NULL,
                created_at INTEGER NOT NULL
            );
            
             CREATE TABLE IF NOT EXISTS failed_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL UNIQUE,
                payload TEXT NOT NULL,
                message TEXT NOT NULL,
                created_at INTEGER NOT NULL
            );
        ");
    }

    public function push($job, ?int $availableAt = null): void
    {
        $query = "INSERT INTO jobs (uuid, payload, available_at, created_at) VALUES (?, ?, ?, ?)";
        $values = [
            Uuid::uuid4()->toString(),
            serialize($job),
            $availableAt,
            time(),
        ];

        $this->pdo->prepare($query)->execute($values);
    }

    public function markAsFailed(array $job, string $message): void
    {
        $query = "INSERT INTO failed_jobs (uuid, payload, message, created_at) VALUES (?, ?, ?, ?)";
        $values = [
            $job['uuid'],
            serialize($job['payload']),
            $message,
            time(),
        ];

        $this->pdo->prepare($query)->execute($values);
    }

    public function next()
    {
        $query = "SELECT * FROM jobs WHERE available_at IS NULL OR available_at <= ? LIMIT 1";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$this->now()]);
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
        $this->push(unserialize($failed_job['payload']));

        // delete from failed_jobs
        $query = "DELETE FROM failed_jobs WHERE uuid = ?";
        $this->pdo->prepare($query)->execute([$uuid]);
    }

    public function isEmpty(): bool
    {
        $query = "SELECT COUNT(*) as count FROM jobs";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data['count'] === 0;
    }

    protected function now(): int
    {
        return time();
    }

    public function attempt($job): ?array
    {
        $job['attempts'] += 1;

        $query = "UPDATE jobs SET attempts = ? WHERE uuid = ?";
        $values = [$job['attempts'], $job['uuid']];

        $this->pdo->prepare($query)->execute($values);

        return $job;
    }

    public function remove(string $uuid): void
    {
        $query = "DELETE FROM jobs WHERE uuid = ?";

        $this->pdo->prepare($query)->execute([$uuid]);
    }

    public function jobs(): array
    {
        $query = "SELECT * FROM jobs";

        return $this->pdo->query($query)->fetchAll();
    }

    public function failedJobs(): array
    {
        $query = "SELECT * FROM failed_jobs";

        return $this->pdo->query($query)->fetchAll();
    }
}