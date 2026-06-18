<?php

namespace Awirhosein\QueueSystem;

interface JobInterface
{
    public function handle(): void;
}