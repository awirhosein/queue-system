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

        $job['attempts'] += 1;
        $this->jobs[0]['attempts'] = $job['attempts'];

        try {
            $job['payload']->handle();

            array_shift($this->jobs);
        } catch (\Exception $e) {

            // retry after fail
            if ($job['attempts'] < $this->max_attempts) {
                $this->run();
                return;
            }

            // remove from jobs
            array_shift($this->jobs);

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
        return ! isset($this->jobs[0]);
    }

    protected function now(): int
    {
        return time();
    }
}