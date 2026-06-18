<?php

namespace Tests\Fixtures;

use Awirhosein\QueueSystem\JobInterface;

class FailedJob implements JobInterface
{
    public function handle(): void
    {
        throw new \Exception('failed job');
    }
}