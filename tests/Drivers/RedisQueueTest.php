<?php

namespace Tests\Drivers;

use Awirhosein\QueueSystem\Drivers\RedisDriver;
use Awirhosein\QueueSystem\Queue;
use Predis\Client;
use Tests\QueueTestCase;

class RedisQueueTest extends QueueTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->refresh();

        $this->driver = new RedisDriver();
        $this->queue = new Queue($this->driver);
    }

    private function refresh(): void
    {
        $client = new Client('tcp://127.0.0.1:6379');
        $client->flushall();
    }
}