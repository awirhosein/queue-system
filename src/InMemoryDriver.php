<?php

namespace Awirhosein\QueueSystem;

use Ramsey\Uuid\Uuid;

class InMemoryDriver implements QueueContract
{
    protected array $jobs = [];
    protected array $failed_jobs = [];

    public function push($job, ?int $availableAt = null): void
    {
        $this->jobs[] = [
            'uuid'         => Uuid::uuid4()->toString(),
            'payload'      => $job,
            'attempts'     => 0,
            'available_at' => $availableAt,
        ];
    }

    public function markAsFailed(array $job, string $message): void
    {
        $this->failed_jobs[] = [
            'uuid'    => $job['uuid'],
            'payload' => $job['payload'],
            'message' => $message,
        ];
    }

    public function next()
    {
        foreach ($this->jobs as $job) {
            if ($job['available_at'] <= $this->now()) {
                return $job;
            }
        }

        return null;
    }

    public function retry(string $uuid): void
    {
        foreach ($this->failed_jobs as $key => $failed_job) {
            if ($failed_job['uuid'] == $uuid) {
                $this->push($failed_job['payload']);
                unset($this->failed_jobs[$key]);
            }
        }
    }

    public function isEmpty(): bool
    {
        return empty($this->jobs);
    }

    protected function now(): int
    {
        return time();
    }

    public function attempt($job): ?array
    {
        foreach ($this->jobs as &$item) {
            if ($item['uuid'] == $job['uuid']) {
                $item['attempts'] += 1;

                return $item;
            }
        }

        return null;
    }

    public function remove(string $uuid): void
    {
        foreach ($this->jobs as $key => $value) {
            if ($value['uuid'] == $uuid) {
                unset($this->jobs[$key]);
            }
        }
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