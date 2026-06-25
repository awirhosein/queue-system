<?php

namespace Tests\Drivers;

use Awirhosein\QueueSystem\Drivers\RedisDriver;
use Awirhosein\QueueSystem\Queue;
use Awirhosein\QueueSystem\Worker;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client;
use Tests\Fixtures\FailedJob;
use Tests\QueueTestCase;

class RedisQueueTest extends QueueTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->refresh();

        $this->driver = new RedisDriver();
        $this->queue = new Queue($this->driver);
        $this->worker = new Worker($this->queue);
    }

    private function refresh(): void
    {
        $client = new Client('tcp://127.0.0.1:6379');
        $client->flushall();
    }

    #[Test]
    public function a_worker_executes_a_delayed_job_when_its_scheduled_time_arrives()
    {
        $future = time() + 60;

        $driver = new class ($future) extends RedisDriver {
            public function __construct(private int $future)
            {
                parent::__construct();
            }

            public function now(): int
            {
                return $this->future + 1;
            }
        };

        $queue = new Queue($driver);
        $worker = new Worker($queue);

        $queue->push(new FailedJob(), $future);
        $worker->runOnce();

        $this->assertCount(1, $queue->failedJobs());
        $this->assertCount(0, $queue->jobs());
    }
}