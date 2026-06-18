<?php

namespace Tests\Fixtures;

use Awirhosein\QueueSystem\JobInterface;

class HandledJob implements JobInterface
{
    public bool $handled = false;

    public function handle(): void
    {
        $this->handled = true;
    }
}