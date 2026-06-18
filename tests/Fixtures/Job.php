<?php

namespace Tests\Fixtures;

use Awirhosein\QueueSystem\JobContract;

class Job implements JobContract
{
    public function handle(): void
    {
        //
    }
}