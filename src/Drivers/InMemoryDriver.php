<?php

namespace Awirhosein\QueueSystem\Drivers;

use Awirhosein\QueueSystem\Contracts\QueueContract;
use Ramsey\Uuid\Uuid;

class InMemoryDriver implements QueueContract
{
    private array $jobs = [];
    private array $failed_jobs = [];

    public function push($job, ?int $availableAt = null, ?string $queue = null, ?int $priority = 0): void
    {
        $this->jobs[] = [
            'uuid'         => Uuid::uuid4()->toString(),
            'queue'        => $queue,
            'payload'      => $job,
            'priority'     => $priority,
            'attempts'     => 0,
            'reserved_at'  => null,
            'available_at' => $availableAt,
        ];
    }

    public function next(?string $queue = null): ?array
    {
        // sort descending by priority
        usort($this->jobs, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        foreach ($this->jobs as &$job) {
            if ($queue && $job['queue'] != $queue) {
                continue;
            }

            if (is_null($job['reserved_at']) && $job['available_at'] <= $this->now()) {
                $job['reserved_at'] = $this->now();

                return $job;
            }
        }

        return null;
    }

    public function attempt(array $job): ?array
    {
        foreach ($this->jobs as &$item) {
            if ($item['uuid'] == $job['uuid']) {
                $item['attempts'] += 1;

                return $item;
            }
        }

        return null;
    }

    public function markAsFailed(array $job, string $message): void
    {
        $this->failed_jobs[] = [
            'uuid'    => $job['uuid'],
            'queue'   => $job['queue'],
            'payload' => $job['payload'],
            'message' => $message,
        ];
    }

    public function remove(string $uuid): void
    {
        foreach ($this->jobs as $key => $value) {
            if ($value['uuid'] == $uuid) {
                unset($this->jobs[$key]);
            }
        }

        $this->jobs = array_values($this->jobs);
    }

    public function retry(string $uuid): void
    {
        foreach ($this->failed_jobs as $key => $failed_job) {
            if ($failed_job['uuid'] == $uuid) {
                $this->push($failed_job['payload'], queue: $failed_job['queue']);
                unset($this->failed_jobs[$key]);
            }
        }
    }

    public function isEmpty(?string $queue = null): bool
    {
        $count = 0;
        foreach ($this->jobs as $job) {
            if ($queue && $job['queue'] != $queue) {
                continue;
            }

            if (is_null($job['reserved_at']) && $job['available_at'] <= $this->now()) {
                $count++;
            }
        }

        return $count === 0;
    }

    protected function now(): int
    {
        return time();
    }

    public function jobs(): array
    {
        return $this->jobs;
    }

    public function failedJobs(): array
    {
        return $this->failed_jobs;
    }
}