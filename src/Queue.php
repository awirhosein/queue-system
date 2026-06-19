<?php

namespace Awirhosein\QueueSystem;

class Queue
{
    protected int $max_attempts = 3;

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
        $job = $this->next();

        if (is_null($job)) {
            return;
        }

        $job = $this->attempt($job);

        try {
            $job['payload']->handle();

            $this->remove($job['uuid']);
        } catch (\Exception $e) {

            // retry after fail
            if ($job['attempts'] < $this->max_attempts) {
                $this->run();
                return;
            }

            $this->remove($job['uuid']);

            $this->addFailedJob($job, $e->getMessage());
        }
    }

    public function addFailedJob(array $job, string $message): void
    {
        $this->driver->markAsFailed($job, $message);
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

    public function attempt($job): ?array
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