<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Events;

use LaunchDarkly\Impl\Util;
use LaunchDarkly\Integrations\Curl;
use LaunchDarkly\Subsystems\EventPublisher;

/**
 * Internal class that processes analytics event data.
 *
 * @ignore
 * @internal
 */
class EventProcessor
{
    private EventPublisher $_eventPublisher;
    private EventSerializer $_eventSerializer;
    private array $_queue = [];
    private int $_capacity;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $sdkKey, array $options)
    {
        $this->_eventPublisher = $this->getEventPublisher($sdkKey, $options);
        $this->_eventSerializer = new EventSerializer($options);

        $this->_capacity = $options['capacity'];
    }

    public function __destruct()
    {
        $this->flush();
    }

    public function sendEvent(array $event): bool
    {
        return $this->enqueue($event);
    }

    /**
     * @param mixed[] $event
     */
    public function enqueue(array $event): bool
    {
        if (count($this->_queue) > $this->_capacity) {
            return false;
        }

        if (isset($event['samplingRatio'])) {
            $samplingRatio = $event['samplingRatio'];
            if (is_int($samplingRatio) && !Util::sample($samplingRatio)) {
                return false;
            }
        }

        $this->_queue[] = $event;

        return true;
    }

    /**
     * Publish events to LaunchDarkly
     * @return bool Whether the events were successfully published
     */
    public function flush(): bool
    {
        if (empty($this->_queue)) {
            return false;
        }

        $payload = $this->_eventSerializer->serializeEvents($this->_queue);

        // We don't expect flush to be called more than once per request cycle, but let's empty the queue just in case
        $this->_queue = [];

        return $this->_eventPublisher->publish($payload);
    }

    /**
     * @psalm-suppress UndefinedClass
     */
    private function getEventPublisher(string $sdkKey, array $options): EventPublisher
    {
        $ep = $options['event_publisher'] ?? null;
        if (!$ep) {
            $ep = Curl::eventPublisher();
        }
        if ($ep instanceof EventPublisher) {
            return $ep;
        }
        if (is_callable($ep)) {
            return $ep($sdkKey, $options);
        }
        if (!is_a($ep, EventPublisher::class, true)) {
            throw new \InvalidArgumentException;
        }
        /**
         * @psalm-suppress LessSpecificReturnStatement
         */
        return new $ep($sdkKey, $options);
    }
}
