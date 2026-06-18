<?php

namespace Tests\Fixtures;

use Awirhosein\QueueSystem\JobContract;

class FailedJob implements JobContract
{
    public function handle(): void
    {
        throw new \Exception('failed job');
    }
}