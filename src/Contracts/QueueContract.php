<?php

namespace Awirhosein\QueueSystem\Contracts;

interface QueueContract
{
    public function push($job, ?int $availableAt = null, ?string $queue = null, ?int $priority = 0): void;

    public function next(?string $queue = null): ?array;

    public function attempt(array $job): ?array;

    public function markAsFailed(array $job, string $message): void;

    public function remove(string $uuid): void;

    public function retry(string $uuid): void;

    public function isEmpty(?string $queue = null): bool;

    public function jobs(): array;

    public function failedJobs(): array;
}