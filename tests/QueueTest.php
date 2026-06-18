<?php

namespace Tests;

use Awirhosein\QueueSystem\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\FailedJob;
use Tests\Fixtures\HandledJob;
use Tests\Fixtures\Job;

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
        $this->queue->push(new Job());

        $this->assertCount(1, $this->queue->jobs);
    }

    #[Test]
    public function a_worker_can_retrieve_the_next_pending_job()
    {
        $this->queue->push(new Job());

        $this->assertInstanceOf(Job::class, $this->queue->next()['payload']);
    }

    #[Test]
    public function a_worker_executes_a_job()
    {
        $job = new HandledJob();
        $this->queue->push($job);

        $this->queue->run();

        $this->assertTrue($job->handled);
    }

    #[Test]
    public function a_failed_job_is_marked_as_failed()
    {
        $this->queue->push(new FailedJob());
        $this->queue->run();

        $this->assertCount(1, $this->queue->failed_jobs);
    }

    #[Test]
    public function a_faild_job_records_the_exception_message()
    {
        $this->queue->push(new FailedJob());
        $this->queue->run();

        $this->assertSame('failed job', $this->queue->failed_jobs[0]['message']);
    }

    #[Test]
    public function a_job_can_be_retried_after_failure()
    {
        $this->queue->push(new FailedJob());
        $this->queue->run();
        $this->assertCount(1, $this->queue->failed_jobs);

        $this->queue->retry($this->queue->failed_jobs[0]['uuid']);
        $this->assertCount(1, $this->queue->jobs);
        $this->assertCount(0, $this->queue->failed_jobs);

        $this->queue->run();
        $this->assertCount(0, $this->queue->jobs);
        $this->assertCount(1, $this->queue->failed_jobs);
    }
}