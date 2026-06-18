<?php

namespace Tests;

use Awirhosein\QueueSystem\Queue;
use Awirhosein\QueueSystem\Worker;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\FailedJob;
use Tests\Fixtures\HandledJob;
use Tests\Fixtures\Job;

class WorkerTest extends TestCase
{
    #[Test]
    public function a_worker_processes_all_jobs_until_queue_is_empty()
    {
        $queue = new Queue();
        $worker = new Worker();

        $queue->push(new Job());
        $queue->push(new FailedJob());
        $queue->push(new FailedJob());
        $queue->push($handledJob = new HandledJob());

        $worker->work($queue);

        $this->assertCount(2, $queue->failed_jobs);
        $this->assertTrue($handledJob->handled);
    }
}