<?php

namespace Awirhosein\QueueSystem;

interface QueueContract
{
    public function push($job, ?int $availableAt = null): void;

    public function run(): void;

    public function next();

    public function retry(string $uuid): void;

    public function isEmpty(): bool;

    public function remove(string $uuid): void;

    public function jobs(): array;

    public function failedJobs(): array;
}