<?php

namespace Awirhosein\QueueSystem;

use Awirhosein\QueueSystem\Contracts\QueueContract;
use Awirhosein\QueueSystem\Drivers\InMemoryDriver;

class Queue
{
    public function __construct(
        public QueueContract $driver = new InMemoryDriver()
    ) {
    }

    public function push($job, ?int $availableAt = null, ?string $queue = null, ?int $priority = 0): void
    {
        $this->driver->push($job, $availableAt, $queue, $priority);
    }

    public function attempt(array $job): ?array
    {
        return $this->driver->attempt($job);
    }

    public function markAsFailed(array $job, string $message): void
    {
        $this->driver->markAsFailed($job, $message);
    }

    public function next(?string $queue = null): ?array
    {
        return $this->driver->next($queue);
    }

    public function retry(string $uuid): void
    {
        $this->driver->retry($uuid);
    }

    public function isEmpty(?string $queue = null): bool
    {
        return $this->driver->isEmpty($queue);
    }

    public function remove(array $job): void
    {
        $this->driver->remove($job);
    }

    public function jobs(): array
    {
        return $this->driver->jobs();
    }

    public function failedJobs(): array
    {
        return $this->driver->failedJobs();
    }
}