<?php

namespace Awirhosein\QueueSystem\Drivers;

use Awirhosein\QueueSystem\Contracts\QueueContract;
use Awirhosein\QueueSystem\Jobs\BaseJob;
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

    public function push(BaseJob $job, ?int $availableAt = null, ?string $queue = null, ?int $priority = 0): void
    {
        $queue ??= 'default';
        $uuid = Uuid::uuid4()->toString();
        $counter = $this->client->incr('queue:push:counter');
        $priority = ($priority * 1e6) + (1e6 - $counter);

        $this->client->sadd('queues', [$queue]);

        if ($availableAt) {
            $this->client->zadd("queue:$queue:delayed", [$uuid => $availableAt]);
        } else {
            $this->client->zadd("queue:$queue", [$uuid => $priority]);
        }

        $this->client->hmset("job:$uuid", [
            'queue'    => $queue,
            'payload'  => serialize($job),
            'priority' => $priority,
            'attempts' => 0,
        ]);
    }

    public function next(?string $queue = null): ?array
    {
        $queue ??= 'default';
        $result = $this->client->zrevrange("queue:$queue", 0, 0);
        if (! empty($result)) {
            return $this->claim($queue, $result[0]);
        }

        $this->reclaimReserved($queue);
        $this->reclaimDelayed($queue);

        $result = $this->client->zrevrange("queue:$queue", 0, 0);
        if (! empty($result)) {
            return $this->claim($queue, $result[0]);
        }

        return null;
    }

    private function claim(string $queue, string $uuid): ?array
    {
        $script = <<<LUA
            if redis.call('ZREM', KEYS[1], ARGV[1]) == 0 then
                return 0
            end
            redis.call('ZADD', KEYS[2], ARGV[2], ARGV[1])
            return 1
        LUA;

        $claimed = $this->client->eval($script, 2,
            "queue:$queue", "queue:$queue:reserved", // KEYS
            $uuid, $this->now() // ARGV
        );

        if (! $claimed) {
            return null;
        }

        $job = $this->client->hgetall("job:$uuid");

        return [
            'uuid'     => $uuid,
            'queue'    => $queue,
            'attempts' => $job['attempts'],
            'payload'  => unserialize($job['payload']),
        ];
    }

    private function reclaimReserved(string $queue): void
    {
        $this->reclaimJobs(
            from: "queue:$queue:reserved",
            to: "queue:$queue",
            maxScore: $this->now() - $this->visibilityTimeout
        );
    }

    private function reclaimDelayed(string $queue): void
    {
        $this->reclaimJobs(
            from: "queue:$queue:delayed",
            to: "queue:$queue",
            maxScore: $this->now()
        );
    }

    private function reclaimJobs(string $from, string $to, int $maxScore): void
    {
        $script = <<<LUA
            local jobs = redis.call('ZRANGEBYSCORE', KEYS[1], 0, ARGV[1])
            for _, uuid in ipairs(jobs) do
                local priority = redis.call('HGET', 'job:' .. uuid, 'priority')
                redis.call('ZADD', KEYS[2], priority, uuid)
                redis.call('ZREM', KEYS[1], uuid)
            end
        LUA;

        $this->client->eval($script, 2,
            $from, // KEYS[1]
            $to, // KEYS[2]
            $maxScore // ARGV[1]
        );
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

        $this->client->hmset("job:$uuid", [
            'queue'   => $queue,
            'payload' => serialize($job['payload']),
            'message' => $message,
        ]);
    }

    public function remove(array $job): void
    {
        $this->client->zrem("queue:{$job['queue']}:reserved", $job['uuid']);
        $this->client->zrem("queue:{$job['queue']}", $job['uuid']);
        $this->client->del("job:{$job['uuid']}");
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
        $queue ??= 'default';

        $this->reclaimReserved($queue);
        $this->reclaimDelayed($queue);

        $res = $this->client->zrange("queue:$queue", 0, 0);

        return empty($res);
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
            $delayedUuids = $this->client->zrange("queue:$queue:delayed", 0, -1);

            $uuids = array_merge($uuids, $delayedUuids);

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