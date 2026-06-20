<?php

namespace Tests;

use Awirhosein\QueueSystem\Queue;
use Awirhosein\QueueSystem\Worker;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\FailedJob;
use Tests\Fixtures\FailingJobWithMaxAttempts;
use Tests\Fixtures\HandledJob;
use Tests\Fixtures\Job;
use Tests\Fixtures\PriorityJob;

abstract class QueueTestCase extends TestCase
{
    protected Queue $queue;

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
        $this->queue->push($job = new HandledJob());

        $this->queue->run();

        $this->assertTrue($job::$handled);
        $this->assertEmpty($this->queue->jobs());
        $this->assertEmpty($this->queue->failedJobs());
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

        $this->assertCount(1, $this->queue->jobs());
        $this->assertCount(0, $this->queue->failedJobs());
    }

    #[Test]
    public function a_worker_processes_all_jobs_until_queue_is_empty()
    {
        $this->queue->push(new Job());
        $this->queue->push(new FailedJob());
        $this->queue->push(new FailedJob());
        $this->queue->push($job = new HandledJob());

        (new Worker())->work($this->queue);

        $this->assertTrue($job::$handled);
        $this->assertEmpty($this->queue->jobs());
        $this->assertCount(2, $this->queue->failedJobs());
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
        $this->queue->push(new FailedJob(), queue: 'first-queue');
        $this->queue->push(new Job(), queue: 'second-queue');

        (new Worker())->work($this->queue, 'second-queue');

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

    #[Test]
    public function a_job_can_define_its_own_max_attempts()
    {
        $job = new FailingJobWithMaxAttempts();
        $job::$count = 0;
        $job->max_attempts = 5;
        $this->queue->push($job);

        $this->queue->run();

        $this->assertSame(5, $job::$count);
        $this->assertCount(0, $this->queue->jobs());
        $this->assertCount(1, $this->queue->failedJobs());
    }

    #[Test]
    public function a_higher_priority_job_is_processed_first()
    {
        $this->queue->push(new PriorityJob('first'), priority: 5);
        $this->queue->push(new PriorityJob('second'), priority: 10);
        $this->queue->run();

        $this->assertSame('second', PriorityJob::$priority);

        $this->queue->push(new PriorityJob('first'), priority: 3);
        $this->queue->push(new PriorityJob('second'), priority: 2);
        $this->queue->run();

        $this->assertSame('first', PriorityJob::$priority);
    }

    #[Test]
    public function same_priority_jobs_maintain_fifo_order()
    {
        $this->queue->push(new PriorityJob('first'), priority: 2);
        $this->queue->push(new PriorityJob('second'), priority: 2);
        $this->queue->run();

        $this->assertSame('first', PriorityJob::$priority);
    }
}