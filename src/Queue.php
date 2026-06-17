<?php

namespace Awirhosein\QueueSystem;

class Queue
{
    protected array $jobs = [];

    public function push($job): void
    {
        $this->jobs[] = $job;
    }

    public function count(): int
    {
        return count($this->jobs);
    }

    public function next()
    {
        if (isset($this->jobs[0])) {
            $job = $this->jobs[0];

            array_shift($this->jobs);

            return $job;
        }

        return null;
    }

    public function run(): void
    {
        $job = $this->next();
        $job->handle();
    }
}