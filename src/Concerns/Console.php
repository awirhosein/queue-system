<?php

namespace Awirhosein\QueueSystem\Concerns;

trait Console
{
    private const string GRAY = "\033[90m";
    private const string GREEN = "\033[32m";
    private const string RED = "\033[31m";
    private const string RESET = "\033[0m";

    private function console(string $color, string $text, array $job): void
    {
        echo PHP_EOL . $color . date('[Y-m-d H:i:s]') . " $text: " . get_class($job['payload']) . self::RESET;
    }
}