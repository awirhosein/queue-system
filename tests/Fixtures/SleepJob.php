<?php

namespace Tests\Fixtures;

use Awirhosein\QueueSystem\Jobs\BaseJob;

class SleepJob extends BaseJob
{
    public function __construct(
        public int $seconds
    ) {
    }

    public function handle(): void
    {
        sleep($this->seconds);
    }
}