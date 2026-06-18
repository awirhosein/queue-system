<?php

namespace Awirhosein\QueueSystem;

class Worker
{
    public function work(Queue $queue): void
    {
        while (! $queue->isEmpty()) {
            $queue->run();
        }
    }
}