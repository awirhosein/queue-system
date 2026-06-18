<?php

namespace Awirhosein\QueueSystem;

class Queue
{
    public function __construct(
        public QueueContract $driver = new InMemoryDriver()
    ) {
    }

    public function push($job, ?int $availableAt = null): void
    {
        $this->driver->push($job, $availableAt);
    }

    public function run(): void
    {
        $this->driver->run();
    }

    public function next()
    {
        return $this->driver->next();
    }

    public function retry(string $uuid): void
    {
        $this->driver->retry($uuid);
    }

    public function isEmpty(): bool
    {
        return $this->driver->isEmpty();
    }

    public function remove(string $uuid): void
    {
        $this->driver->remove($uuid);
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