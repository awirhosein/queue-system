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
        // jobs
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL UNIQUE,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                available_at INTEGER NULL,
                created_at INTEGER NOT NULL
            );
        ");

        // failed_jobs
        $this->pdo->exec("
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
        $stmt = $this->pdo->prepare(
            "INSERT INTO jobs (uuid, payload, attempts, available_at, created_at)
            VALUES (?, ?, ?, ?, ?);"
        );

        $stmt->execute([
            Uuid::uuid4()->toString(),
            serialize($job),
            0,
            $availableAt,
            time(),
        ]);
    }

    public function markAsFailed(array $job, string $message): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO failed_jobs (uuid, payload, message, created_at)
            VALUES (?, ?, ?, ?)"
        );

        $stmt->execute([
            $job['uuid'],
            serialize($job['payload']),
            $message,
            time(),
        ]);
    }

    public function next()
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM jobs
            WHERE available_at IS NULL OR available_at <= ?
            LIMIT 1"
        );

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
        $stmt = $this->pdo->prepare(
            "SELECT * FROM failed_jobs WHERE uuid = ?"
        );
        $stmt->execute([$uuid]);
        $failed_job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $failed_job) {
            return;
        }

        $this->push(unserialize($failed_job['payload']));

        $stmt = $this->pdo->prepare(
            "DELETE FROM failed_jobs WHERE uuid = ?"
        );

        $stmt->execute([$uuid]);
    }

    public function isEmpty(): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as count FROM jobs"
        );

        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] === 0;
    }

    protected function now(): int
    {
        return time();
    }

    public function attempt($job): ?array
    {
        $job['attempts'] += 1;

        $stmt = $this->pdo->prepare(
            "UPDATE jobs SET attempts = ? WHERE uuid = ?"
        );

        $stmt->execute([
            $job['attempts'], $job['uuid'],
        ]);

        return $job;
    }

    public function remove(string $uuid): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM jobs WHERE uuid = ?"
        );

        $stmt->execute([$uuid]);
    }

    public function jobs(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM jobs");

        return $stmt->fetchAll();
    }

    public function failedJobs(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM failed_jobs");

        return $stmt->fetchAll();
    }
}