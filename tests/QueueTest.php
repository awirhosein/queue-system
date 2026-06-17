<?php

namespace Tests;

use Awirhosein\QueueSystem\Queue;
use PHPUnit\Framework\Attributes\Test;

class QueueTest extends TestCase
{
    #[Test]
    public function a_job_can_be_pushed_to_the_queue()
    {
        $queue = new Queue();

        $queue->push('job');

        $this->assertSame(1, $queue->count());
    }

    #[Test]
    public function a_worker_can_retrieve_the_next_pending_job()
    {
        $queue = new Queue();

        $queue->push('job');

        $this->assertSame('job', $queue->next());
    }

    #[Test]
    public function a_worker_executes_a_job()
    {
        $queue = new Queue();
        $job = new class {
            public bool $handled = false;

            public function handle(): void
            {
                $this->handled = true;
            }
        };

        $queue->push($job);
        $queue->run();

        $this->assertTrue($job->handled);
    }
}