<?php

namespace Awirhosein\QueueSystem\Jobs;

abstract class BaseJob
{
    public int $max_attempts = 3;
    public int $timeout = 60;

    public abstract function handle(): void;
}