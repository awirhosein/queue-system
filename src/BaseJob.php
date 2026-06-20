<?php

namespace Awirhosein\QueueSystem;

abstract class BaseJob
{
    public int $max_attempts = 3;

    public abstract function handle(): void;
}