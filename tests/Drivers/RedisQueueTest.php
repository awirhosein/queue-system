<?php

namespace Tests\Drivers;

use Awirhosein\QueueSystem\Contracts\QueueContract;
use Awirhosein\QueueSystem\Drivers\RedisDriver;
use Awirhosein\QueueSystem\Queue;
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

        $queue = new class ($driver) extends Queue {
            public function __construct(public QueueContract $driver)
            {
                parent::__construct($driver);
            }
        };

        $queue->push(new FailedJob(), $future);
        $queue->run();

        $this->assertCount(1, $queue->failedJobs());
        $this->assertCount(0, $queue->jobs());
    }
}