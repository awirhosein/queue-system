<?php

namespace Awirhosein\QueueSystem\Drivers;

use Awirhosein\QueueSystem\Contracts\QueueContract;
use Predis\Client;
use Ramsey\Uuid\Uuid;

class RedisDriver implements QueueContract
{
    protected Client $client;
    public int $visibilityTimeout = 60;

    public function __construct()
    {
        $this->client = new Client('tcp://127.0.0.1:6379');
    }

    public function push($job, ?int $availableAt = null, ?string $queue = null, ?int $priority = 0): void
    {
        $queue ??= 'default';
        $uuid = Uuid::uuid4()->toString();
        $counter = $this->client->incr('queue:push:counter');
        $priority = ($priority * 1e6) + (1e6 - $counter);

        $this->client->sadd('queues', [$queue]);
        $this->client->zadd("queue:$queue", [$uuid => $priority]);

        // job data
        $this->client->hset("job:$uuid", 'queue', $queue);
        $this->client->hset("job:$uuid", 'payload', serialize($job));
        $this->client->hset("job:$uuid", 'priority', $priority);
        $this->client->hset("job:$uuid", 'attempts', 0);
        $this->client->hset("job:$uuid", 'reserved_at', null);
        $this->client->hset("job:$uuid", 'available_at', $availableAt);
    }

    public function next(?string $queue = null): ?array
    {
        $queue ??= 'default';
        $uuids = $this->client->zrevrange("queue:$queue", 0, -1);

        foreach ($uuids as $uuid) {
            $job = $this->client->hgetall("job:$uuid");

            $isAvailable = ($job['reserved_at'] ?? 0) <= $this->now() - $this->visibilityTimeout;
            $isDue = ($job['available_at'] ?? 0) <= $this->now();

            if ($isAvailable && $isDue) {
                $this->client->hset("job:$uuid", 'reserved_at', $this->now());

                return [
                    ...$job,
                    'uuid'    => $uuid,
                    'payload' => unserialize($job['payload']),
                ];
            }
        }

        return null;
    }

    public function attempt(array $job): ?array
    {
        $job['attempts'] += 1;
        $this->client->hset("job:{$job['uuid']}", 'attempts', $job['attempts']);

        return $job;
    }

    public function markAsFailed(array $job, string $message): void
    {
        $uuid = $job['uuid'];
        $queue = $job['queue'] ?? 'default';

        $this->client->lpush("queue:$queue:failed", $uuid);

        $this->client->hset("job:$uuid", 'queue', $queue);
        $this->client->hset("job:$uuid", 'payload', serialize($job['payload']));
        $this->client->hset("job:$uuid", 'message', $message);
    }

    public function remove(string $uuid): void
    {
        $job = $this->client->hgetall("job:$uuid");
        $this->client->zrem("queue:{$job['queue']}", $uuid);
        $this->client->del("job:$uuid");
    }

    public function retry(string $uuid): void
    {
        $failedJob = $this->client->hgetall("job:$uuid");

        $remove = $this->client->lrem("queue:{$failedJob['queue']}:failed", 1, $uuid);
        if ($remove !== 1) {
            return;
        }

        $this->push(
            unserialize($failedJob['payload']),
            queue: $failedJob['queue']
        );
    }

    public function isEmpty(?string $queue = null): bool
    {
        $count = 0;
        $queue ??= 'default';
        $uuids = $this->client->zrange("queue:$queue", 0, -1);

        foreach ($uuids as $uuid) {
            $job = $this->client->hgetall("job:$uuid");

            if (($job['available_at'] ?? 0) <= $this->now()) {
                $count++;
            }
        }

        return $count === 0;
    }

    public function now(): int
    {
        return time();
    }

    public function jobs(): array
    {
        $jobs = [];

        foreach ($this->client->smembers('queues') as $queue) {
            $uuids = $this->client->zrange("queue:$queue", 0, -1);

            foreach ($uuids as $uuid) {
                $job = $this->client->hgetall("job:$uuid");

                $jobs[] = [
                    ...$job,
                    'uuid'    => $uuid,
                    'payload' => unserialize($job['payload']),
                ];
            }
        }

        return $jobs;
    }

    public function failedJobs(): array
    {
        $failedJobs = [];

        foreach ($this->client->smembers('queues') as $queue) {
            $uuids = $this->client->lrange("queue:$queue:failed", 0, -1);

            foreach ($uuids as $uuid) {
                $job = $this->client->hgetall("job:$uuid");

                $failedJobs[] = [
                    'uuid'    => $uuid,
                    'queue'   => $job['queue'],
                    'message' => $job['message'],
                    'payload' => unserialize($job['payload']),
                ];
            }
        }

        return $failedJobs;
    }
}