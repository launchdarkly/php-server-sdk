<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Events;

/**
 * @ignore
 * @internal
 */
class NullEventProcessor extends EventProcessor
{
    public function enqueue(array $event): bool
    {
        return true;
    }

    public function flush(): bool
    {
        return true;
    }
}
