<?php

namespace Awirhosein\QueueSystem;

interface JobContract
{
    public function handle(): void;
}