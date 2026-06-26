<?php

namespace Awirhosein\QueueSystem\Jobs;

abstract class BaseJob
{
    public int $maxAttempts = 3;
    public int $timeout = 60;

    public abstract function handle(): void;
}