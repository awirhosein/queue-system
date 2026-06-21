<?php

namespace Awirhosein\QueueSystem;

use Awirhosein\QueueSystem\Contracts\QueueContract;
use Awirhosein\QueueSystem\Drivers\InMemoryDriver;

class Queue
{
    private const string GRAY = "\033[90m";
    private const string GREEN = "\033[32m";
    private const string RED = "\033[31m";
    private const string RESET = "\033[0m";

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

        while (true) {
            $job = $this->attempt($job);

            try {
                $this->console(self::GRAY, 'Processing', $job);

                $job['payload']->handle();
                $this->remove($job['uuid']);

                $this->console(self::GREEN, 'Processed', $job);
                break;
            } catch (\Exception $e) {
                if ($job['attempts'] >= $job['payload']->max_attempts) {
                    $this->remove($job['uuid']);
                    $this->markAsFailed($job, $e->getMessage());

                    $this->console(self::RED, 'Failed', $job);
                    break;
                }
            }
        }
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

    private function console(string $color, string $text, array $job): void
    {
        echo PHP_EOL . $color . date('[Y-m-d H:i:s]') . " $text: " . get_class($job['payload']) . self::RESET;
    }
}