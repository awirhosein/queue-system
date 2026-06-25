<?php

namespace Awirhosein\QueueSystem;

pcntl_async_signals(true);

use Awirhosein\QueueSystem\Concerns\Console;
use Awirhosein\QueueSystem\Exceptions\TimeoutException;

class Worker
{
    use Console;

    public function __construct(
        public Queue $queue
    ) {
    }

    public function runOnce(?string $queueName = null): void
    {
        $this->run($queueName);
    }

    public function work(?string $queueName = null): void
    {
        while (! $this->queue->isEmpty($queueName)) {
            $this->run($queueName);
        }
    }

    public function daemon(?string $queueName = null): void
    {
        $running = true;

        pcntl_signal(SIGTERM, function () use (&$running) {
            $running = false;
        });
        pcntl_signal(SIGINT, function () use (&$running) {
            $running = false;
        });

        while ($running) {
            $this->run($queueName);
            pcntl_signal_dispatch();

            if ($running && $this->queue->isEmpty($queueName)) {
                sleep(1);
            }
        }
    }

    private function run(?string $queueName = null): void
    {
        $job = $this->queue->next($queueName);

        if (is_null($job)) {
            return;
        }

        while (true) {
            $job = $this->queue->attempt($job);
            $jobInstance = $job['payload'];

            pcntl_signal(SIGALRM, fn () => throw new TimeoutException());
            pcntl_alarm($jobInstance->timeout);

            try {
                $this->console(self::GRAY, 'Processing', $job);

                $jobInstance->handle();
                $this->queue->remove($job);

                $this->console(self::GREEN, 'Processed', $job);
                break;
            } catch (\Exception $e) {
                if ($job['attempts'] >= $jobInstance->max_attempts) {
                    $this->queue->remove($job);
                    $this->queue->markAsFailed($job, $e->getMessage());

                    $this->console(self::RED, 'Failed', $job);
                    break;
                }
            } finally {
                pcntl_alarm(0);
            }
        }
    }
}