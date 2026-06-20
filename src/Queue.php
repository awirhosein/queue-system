<?php

namespace Awirhosein\QueueSystem;

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

    public function run(?string $queue = null): void
    {
        $job = $this->next($queue);

        if (is_null($job)) {
            return;
        }

        $job = $this->attempt($job);

        try {
            $job['payload']->handle();
            $this->remove($job['uuid']);

        } catch (\Exception $e) {

            // retry after fail
            if ($job['attempts'] < $job['payload']->max_attempts) {
                $this->run();
                return;
            }

            $this->remove($job['uuid']);
            $this->markAsFailed($job, $e->getMessage());
        }
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

    public function attempt(array $job): ?array
    {
        return $this->driver->attempt($job);
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