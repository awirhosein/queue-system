<?php

namespace Tests\Drivers;

use Awirhosein\QueueSystem\Drivers\InMemoryDriver;
use Awirhosein\QueueSystem\Queue;
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

        $queue->push(new FailedJob(), $future);
        $queue->run();

        $this->assertCount(1, $queue->failedJobs());
        $this->assertCount(0, $queue->jobs());
    }
}