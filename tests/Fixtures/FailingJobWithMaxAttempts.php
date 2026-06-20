<?php

namespace Tests\Fixtures;

use Awirhosein\QueueSystem\BaseJob;

class FailingJobWithMaxAttempts extends BaseJob
{
    public static int $count = 0;

    public function handle(): void
    {
        static::$count++;

        throw new \Exception('fail');
    }
}