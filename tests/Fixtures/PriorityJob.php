<?php

namespace Tests\Fixtures;

use Awirhosein\QueueSystem\BaseJob;

class PriorityJob extends BaseJob
{
    public static string $priority;

    public function __construct(
        public $value
    ) {
    }

    public function handle(): void
    {
        static::$priority = $this->value;
    }
}