<?php
namespace LaunchDarkly\Impl;

class NullEventProcessor
{
    public function enqueue($event)
    {
        return true;
    }

    public function flush()
    {
    }
}
