<?php

namespace Tests\Drivers;

use Awirhosein\QueueSystem\Drivers\InMemoryDriver;
use Awirhosein\QueueSystem\Queue;
use Awirhosein\QueueSystem\Worker;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\FailedJob;
use Tests\QueueTestCase;

class InMemoryQueueTest extends QueueTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new InMemoryDriver();
        $this->queue = new Queue($this->driver);
        $this->worker = new Worker($this->queue);
    }

    #[Test]
    public function a_worker_executes_a_delayed_job_when_its_scheduled_time_arrives()
    {
        $future = time() + 60;

        $driver = new class ($future) extends InMemoryDriver {
            public function __construct(private int $future)
            {
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