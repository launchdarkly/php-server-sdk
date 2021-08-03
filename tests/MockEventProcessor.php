<?php
namespace LaunchDarkly\Tests;

class MockEventProcessor extends \LaunchDarkly\EventProcessor
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
