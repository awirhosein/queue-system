<?php

namespace Tests;

use Awirhosein\QueueSystem\Queue;
use PHPUnit\Framework\Attributes\Test;

class QueueTest extends TestCase
{
    protected Queue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new Queue();
    }

    #[Test]
    public function a_job_can_be_pushed_to_the_queue()
    {
        $this->queue->push('job');

        $this->assertCount(1, $this->queue->jobs);
    }

    #[Test]
    public function a_worker_can_retrieve_the_next_pending_job()
    {
        $this->queue->push('job');

        $this->assertSame('job', $this->queue->next()['payload']);
    }

    #[Test]
    public function a_worker_executes_a_job()
    {
        $job = new class {
            public bool $handled = false;

            public function handle(): void
            {
                $this->handled = true;
            }
        };

        $this->queue->push($job);
        $this->queue->run();

        $this->assertTrue($job->handled);
    }

    #[Test]
    public function a_failed_job_is_marked_as_failed()
    {
        $job = new class {
            public function handle(): void
            {
                throw new \Exception('failed job');
            }
        };

        $this->queue->push($job);
        $this->queue->run();

        $this->assertCount(1, $this->queue->failed_jobs);
    }

    #[Test]
    public function a_faild_job_records_the_exception_message()
    {
        $job = new class {
            public function handle(): void
            {
                throw new \Exception('failed job');
            }
        };

        $this->queue->push($job);
        $this->queue->run();

        $this->assertSame('failed job', $this->queue->failed_jobs[0]['message']);
    }

    #[Test]
    public function a_job_can_be_retried_after_failure()
    {
        $job = new class {
            public function handle(): void
            {
                throw new \Exception('failed job');
            }
        };

        $this->queue->push($job);
        $this->queue->run();

        $this->assertCount(1, $this->queue->failed_jobs);

        $this->queue->retry($this->queue->failed_jobs[0]['uuid']);

        $this->assertCount(0, $this->queue->failed_jobs);
        $this->assertCount(1, $this->queue->jobs);

        $this->queue->run();

        $this->assertCount(1, $this->queue->failed_jobs);
        $this->assertCount(0, $this->queue->jobs);
    }
}