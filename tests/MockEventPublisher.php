<?php
namespace LaunchDarkly\Tests;

class MockEventPublisher implements \LaunchDarkly\EventPublisher
{
    public $payloads = array();

    public function __construct($sdkKey, array $options)
    {
    }

    public function publish($payload)
    {
        $this->payloads[] = $payload;
    }
}
