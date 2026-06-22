<?php

namespace Tests\Drivers;

use Awirhosein\QueueSystem\Contracts\QueueContract;
use Awirhosein\QueueSystem\Drivers\DatabaseDriver;
use Awirhosein\QueueSystem\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\FailedJob;
use Tests\QueueTestCase;

class DatabaseQueueTest extends QueueTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();

        $this->driver = new DatabaseDriver();
        $this->queue = new Queue($this->driver);
    }

    protected function refreshDatabase(): void
    {
        $pdo = new \PDO('sqlite:' . __DIR__ . '/../../database/queue.sqlite');
        $pdo->exec("
            DROP TABLE IF EXISTS jobs;
            DROP TABLE IF EXISTS failed_jobs;
        ");
    }

    #[Test]
    public function a_worker_executes_a_delayed_job_when_its_scheduled_time_arrives()
    {
        $future = time() + 60;

        $driver = new class ($future) extends DatabaseDriver {
            public function __construct(private int $future)
            {
                parent::__construct();
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