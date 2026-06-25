<?php

namespace Tests;

use Awirhosein\QueueSystem\Contracts\QueueContract;
use Awirhosein\QueueSystem\Queue;
use Awirhosein\QueueSystem\Worker;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\FailedJob;
use Tests\Fixtures\FailingJobWithMaxAttempts;
use Tests\Fixtures\HandledJob;
use Tests\Fixtures\Job;
use Tests\Fixtures\PriorityJob;
use Tests\Fixtures\SleepJob;

abstract class QueueTestCase extends TestCase
{
    protected QueueContract $driver;
    protected Queue $queue;
    protected Worker $worker;

    #[Test]
    public function a_job_can_be_pushed_to_the_queue()
    {
        $this->queue->push(new Job());

        $this->assertCount(1, $this->queue->jobs());
    }

    #[Test]
    public function the_next_pending_job_can_be_retrieved()
    {
        $this->queue->push(new Job());

        $this->assertInstanceOf(Job::class, $this->queue->next()['payload']);
    }

    #[Test]
    public function a_job_can_be_processed()
    {
        $this->queue->push($job = new HandledJob());

        $this->worker->runOnce();

        $this->assertTrue($job::$handled);
        $this->assertEmpty($this->queue->jobs());
        $this->assertEmpty($this->queue->failedJobs());
    }

    #[Test]
    public function a_failed_job_is_marked_as_failed()
    {
        $this->queue->push(new FailedJob());
        $this->worker->runOnce();

        $this->assertCount(1, $this->queue->failedJobs());
    }

    #[Test]
    public function a_failed_job_records_the_exception_message()
    {
        $this->queue->push(new FailedJob());
        $this->worker->runOnce();

        $this->assertSame('failed job', $this->queue->failedJobs()[0]['message']);
    }

    #[Test]
    public function a_job_can_be_retried_after_failure()
    {
        $this->queue->push(new FailedJob());
        $this->worker->runOnce();
        $this->assertCount(1, $this->queue->failedJobs());

        $this->queue->retry($this->queue->failedJobs()[0]['uuid']);
        $this->assertCount(1, $this->queue->jobs());
        $this->assertCount(0, $this->queue->failedJobs());

        $this->worker->runOnce();
        $this->assertCount(0, $this->queue->jobs());
        $this->assertCount(1, $this->queue->failedJobs());
    }

    #[Test]
    public function delayed_jobs_are_skipped_during_processing()
    {
        $future = time() + 60;
        $this->queue->push(new FailedJob(), $future);
        $this->worker->runOnce();

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

        $this->worker->work();

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

        $this->worker->runOnce('second-queue');

        $this->assertCount(1, $this->queue->jobs());
        $this->assertCount(0, $this->queue->failedJobs());
    }

    #[Test]
    public function a_worker_can_run_a_specific_queue()
    {
        $this->queue->push(new FailedJob(), queue: 'first-queue');
        $this->queue->push(new Job(), queue: 'second-queue');

        $this->worker->work('second-queue');

        $this->assertCount(1, $this->queue->jobs());
        $this->assertCount(0, $this->queue->failedJobs());
    }

    #[Test]
    public function a_failed_job_after_retry_goes_back_to_the_same_queue()
    {
        $this->queue->push(new FailedJob(), queue: 'queue-name');
        $this->worker->runOnce('queue-name');
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

        $this->worker->runOnce();

        $this->assertSame(5, $job::$count);
        $this->assertCount(0, $this->queue->jobs());
        $this->assertCount(1, $this->queue->failedJobs());
    }

    #[Test]
    public function a_higher_priority_job_is_processed_first()
    {
        $this->queue->push(new PriorityJob('first'), priority: 5);
        $this->queue->push(new PriorityJob('second'), priority: 10);
        $this->worker->runOnce();

        $this->assertSame('second', PriorityJob::$priority);

        $this->queue->push(new PriorityJob('first'), priority: 3);
        $this->queue->push(new PriorityJob('second'), priority: 2);
        $this->worker->runOnce();

        $this->assertSame('first', PriorityJob::$priority);
    }

    #[Test]
    public function jobs_with_equal_priority_are_processed_fifo()
    {
        $this->queue->push(new PriorityJob('first'), priority: 2);
        $this->queue->push(new PriorityJob('second'), priority: 2);
        $this->worker->runOnce();

        $this->assertSame('first', PriorityJob::$priority);
    }

    #[Test]
    public function a_job_is_not_returned_twice()
    {
        $this->queue->push(new Job());
        $this->queue->push(new Job());

        $job1 = $this->queue->next();
        $job2 = $this->queue->next();

        $this->assertNotNull($job1);
        $this->assertNotNull($job2);
        $this->assertNotSame($job1['uuid'], $job2['uuid']);
    }

    #[Test]
    public function a_job_will_fail_after_timeout()
    {
        $job = new SleepJob(2);
        $job->timeout = 1;
        $job->max_attempts = 1;
        $this->queue->push($job);

        $this->worker->runOnce();

        $this->assertCount(0, $this->queue->jobs());
        $this->assertCount(1, $this->queue->failedJobs());
    }

    #[Test]
    public function a_reserved_job_is_reclaimed_after_visibility_timeout()
    {
        //
        $this->queue->push(new Job(), queue: 'queue-name');
        $first = $this->queue->next('queue-name');
        $second = $this->queue->next('queue-name');

        $this->assertNotNull($first);
        $this->assertNull($second);

        //
        $driver = new $this->driver;
        $driver->visibilityTimeout = 0;
        $queue = new Queue($driver);

        $queue->push(new Job());
        $first = $queue->next();
        $second = $queue->next();

        $this->assertSame($first['uuid'], $second['uuid']);
    }
}