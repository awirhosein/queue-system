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
        $running = true;

        pcntl_signal(SIGTERM, function () use (&$running) {
            $running = false;
        });
        pcntl_signal(SIGINT, function () use (&$running) {
            $running = false;
        });

        while ($running) {
            $queue->run($queueName);
            pcntl_signal_dispatch();

            if ($running && $queue->isEmpty($queueName)) {
                sleep(1);
            }
        }
    }
}