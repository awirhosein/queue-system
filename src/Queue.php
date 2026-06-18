<?php

namespace Awirhosein\QueueSystem;

use Ramsey\Uuid\Uuid;

class Queue
{
    public array $jobs = [];
    public array $failed_jobs = [];
    public int $max_attempts = 3;

    public function push($job, ?int $availableAt = null): void
    {
        $this->jobs[] = [
            'uuid'         => Uuid::uuid4()->toString(),
            'payload'      => $job,
            'attempts'     => 0,
            'available_at' => $availableAt,
        ];
    }

    public function run(): void
    {
        $job = $this->next();

        if (is_null($job)) {
            return;
        }

        $job = $this->attempt($job['uuid']);

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

            // add to failed jobs
            $this->failed_jobs[] = [
                'uuid'    => $job['uuid'],
                'payload' => $job['payload'],
                'message' => $e->getMessage(),
            ];
        }
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

    protected function attempt(string $uuid): ?array
    {
        foreach ($this->jobs as &$job) {
            if ($job['uuid'] == $uuid) {
                $job['attempts'] += 1;

                return $job;
            }
        }

        return null;
    }

    protected function remove(string $uuid): void
    {
        foreach ($this->jobs as $key => $value) {
            if ($value['uuid'] == $uuid) {
                unset($this->jobs[$key]);
            }
        }
    }
}