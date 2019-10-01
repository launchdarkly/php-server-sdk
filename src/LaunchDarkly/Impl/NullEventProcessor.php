<?php
namespace LaunchDarkly;

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
