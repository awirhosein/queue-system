<?php

namespace Awirhosein\QueueSystem;

class Worker
{
    public function work(Queue $queue, ?string $queueName = null): void
    {
        while (! $queue->isEmpty($queueName)) {
            $queue->run($queueName);
        }
    }

    public function runOnce(Queue $queue, ?string $queueName = null): void
    {
        $queue->run($queueName);
    }

    public function daemon(Queue $queue, ?string $queueName = null): void
    {
        while (true) {
            $queue->run($queueName);

            if ($queue->isEmpty($queueName)) {
                sleep(1);
            }
        }
    }
}