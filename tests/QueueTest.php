<?php

namespace Tests;

use Awirhosein\QueueSystem\InMemoryDriver;
use Awirhosein\QueueSystem\Queue;
use Awirhosein\QueueSystem\QueueContract;
use Awirhosein\QueueSystem\Worker;
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

        $this->assertCount(1, $this->queue->jobs());
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

        $this->assertCount(1, $this->queue->failedJobs());
    }

    #[Test]
    public function a_failed_job_records_the_exception_message()
    {
        $this->queue->push(new FailedJob());
        $this->queue->run();

        $this->assertSame('failed job', $this->queue->failedJobs()[0]['message']);
    }

    #[Test]
    public function a_job_can_be_retried_after_failure()
    {
        $this->queue->push(new FailedJob());
        $this->queue->run();
        $this->assertCount(1, $this->queue->failedJobs());

        $this->queue->retry($this->queue->failedJobs()[0]['uuid']);
        $this->assertCount(1, $this->queue->jobs());
        $this->assertCount(0, $this->queue->failedJobs());

        $this->queue->run();
        $this->assertCount(0, $this->queue->jobs());
        $this->assertCount(1, $this->queue->failedJobs());
    }

    #[Test]
    public function a_worker_ignores_jobs_that_are_not_yet_available()
    {
        $future = time() + 60;
        $this->queue->push(new FailedJob(), $future);
        $this->queue->run();

        $this->assertCount(0, $this->queue->failedJobs());
        $this->assertCount(1, $this->queue->jobs());
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

    #[Test]
    public function a_worker_processes_all_jobs_until_queue_is_empty()
    {
        $worker = new Worker();

        $this->queue->push(new Job());
        $this->queue->push(new FailedJob());
        $this->queue->push(new FailedJob());
        $this->queue->push($handledJob = new HandledJob());

        $worker->work($this->queue);

        $this->assertCount(2, $this->queue->failedJobs());
        $this->assertTrue($handledJob->handled);
    }

    #[Test]
    public function a_job_can_be_assigned_to_a_named_queue()
    {
        $this->queue->push(new Job(), queue: 'queue-name');

        $this->assertSame('queue-name', $this->queue->jobs()[0]['queue']);
    }

    #[Test]
    public function jobs_from_other_queues_are_ignored()
    {
        $this->queue->push(new FailedJob(), queue: 'first-queue');
        $this->queue->push(new Job(), queue: 'second-queue');

        $this->queue->run('second-queue');

        $this->assertCount(1, $this->queue->jobs());
        $this->assertCount(0, $this->queue->failedJobs());
    }

    #[Test]
    public function a_worker_can_run_a_specific_queue()
    {
        $worker = new Worker();

        $this->queue->push(new FailedJob(), queue: 'first-queue');
        $this->queue->push(new Job(), queue: 'second-queue');

        $worker->work($this->queue, 'second-queue');

        $this->assertCount(1, $this->queue->jobs());
        $this->assertCount(0, $this->queue->failedJobs());
    }

    #[Test]
    public function a_failed_job_after_retry_goes_back_to_the_same_queue()
    {
        $this->queue->push(new FailedJob(), queue: 'queue-name');
        $this->queue->run('queue-name');

        $this->assertCount(1, $this->queue->failedJobs());

        $this->queue->retry($this->queue->failedJobs()[0]['uuid']);

        $this->assertSame('queue-name', $this->queue->jobs()[0]['queue']);
    }
}