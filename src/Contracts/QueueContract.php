<?php

namespace Awirhosein\QueueSystem\Contracts;

use Awirhosein\QueueSystem\Jobs\BaseJob;

interface QueueContract
{
    public function push(BaseJob $job, ?int $availableAt = null, ?string $queue = null, ?int $priority = 0): void;

    public function next(?string $queue = null): ?array;

    public function attempt(array $job): ?array;

    public function markAsFailed(array $job, string $message): void;

    public function remove(array $job): void;

    public function retry(string $uuid): void;

    public function isEmpty(?string $queue = null): bool;

    public function now(): int;

    public function jobs(): array;

    public function failedJobs(): array;
}