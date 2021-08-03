<?php
namespace LaunchDarkly\Impl;

class NullEventProcessor extends \LaunchDarkly\EventProcessor
{
    public function enqueue($event): bool
    {
        return true;
    }

    public function flush(): bool
    {
        return true;
    }
}
