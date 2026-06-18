<?php

namespace Tests\Fixtures;

use Awirhosein\QueueSystem\JobInterface;

class Job implements JobInterface
{
    public function handle(): void
    {
        //
    }
}