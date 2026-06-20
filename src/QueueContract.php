<?php

namespace Awirhosein\QueueSystem;

interface QueueContract
{
    public function push($job, ?int $availableAt = null, ?string $queue = null, ?int $priority = 0): void;

    public function markAsFailed(array $job, string $message): void;

    public function next(?string $queue = null): ?array;

    public function retry(string $uuid): void;

    public function isEmpty(?string $queue = null): bool;

    public function attempt(array $job): ?array;

    public function remove(string $uuid): void;

    public function jobs(): array;

    public function failedJobs(): array;
}