<?php

namespace Tests\Fixtures;

use Awirhosein\QueueSystem\BaseJob;

class HandledJob extends BaseJob
{
    public static bool $handled = false;

    public function handle(): void
    {
        static::$handled = true;
    }
}