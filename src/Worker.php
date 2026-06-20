<?php

namespace Awirhosein\QueueSystem;

class Worker
{
    public function work(Queue $queue, ?string $name = null): void
    {
        while (! $queue->isEmpty($name)) {
            $queue->run($name);
        }
    }
}