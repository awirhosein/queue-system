<?php

namespace Tests\Fixtures;

use Awirhosein\QueueSystem\BaseJob;

class FailedJob extends BaseJob
{
    public function handle(): void
    {
        throw new \Exception('failed job');
    }
}