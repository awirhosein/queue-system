<?php

namespace Tests;

use Awirhosein\QueueSystem\InMemoryDriver;
use Awirhosein\QueueSystem\Queue;
use Awirhosein\QueueSystem\QueueContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\FailedJob;

class InMemoryQueueTest extends QueueTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new Queue(
            new InMemoryDriver()
        );
    }

    #[Test]
    public function a_worker_executes_a_delayed_job_when_its_scheduled_time_arrives()
    {
        $future = time() + 60;

        $driver = new class ($future) extends InMemoryDriver {
            public function __construct(private int $future)
            {
            }

            protected function now(): int
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