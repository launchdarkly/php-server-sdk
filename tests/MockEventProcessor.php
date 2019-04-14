<?php
namespace LaunchDarkly\Tests;

class MockEventProcessor
{
    private $_events;

    public function __construct()
    {
        $this->_events = array();
    }

    public function enqueue($event)
    {
        $this->_events[] = $event;
        return true;
    }

    public function flush()
    {
    }

    public function getEvents()
    {
        return $this->_events;
    }
}
