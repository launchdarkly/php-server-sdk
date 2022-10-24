<?php

namespace LaunchDarkly\Tests;

class MockEventPublisher implements \LaunchDarkly\Subsystems\EventPublisher
{
    public $payloads = [];

    public function __construct(string $sdkKey, array $options)
    {
    }

    public function publish(string $payload): bool
    {
        $this->payloads[] = $payload;
        return true;
    }
}
