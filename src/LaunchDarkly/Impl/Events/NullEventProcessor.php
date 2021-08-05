<?php
namespace LaunchDarkly\Impl\Events;

class NullEventProcessor extends EventProcessor
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
