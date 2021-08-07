<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\Impl\Events\EventProcessor;

class MockEventProcessor extends EventProcessor
{
    private $_events;

    public function __construct()
    {
        $this->_events = array();
    }

    public function enqueue($event): bool
    {
        $this->_events[] = $event;
        return true;
    }

    public function flush(): bool
    {
        return true;
    }

    public function getEvents(): array
    {
        return $this->_events;
    }
}
