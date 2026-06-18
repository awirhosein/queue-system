<?php

namespace Tests\Fixtures;

use Awirhosein\QueueSystem\JobContract;

class HandledJob implements JobContract
{
    public bool $handled = false;

    public function handle(): void
    {
        $this->handled = true;
    }
}