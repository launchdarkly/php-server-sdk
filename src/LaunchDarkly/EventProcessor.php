<?php
namespace LaunchDarkly;

use LaunchDarkly\Integrations\Curl;

/**
 * Internal class that processes analytics event data.
 *
 * @ignore
 * @internal
 */
class EventProcessor
{
    /** @var EventPublisher */
    private $_eventPublisher;

    /** @var EventSerializer */
    private $_eventSerializer;

    /** @var array */
    private $_queue = [];

    /** @var int */
    private $_capacity;

    /** @var int */
    private $_timeout;

    public function __construct(string $sdkKey, array $options = array())
    {
        $this->_eventPublisher = $this->getEventPublisher($sdkKey, $options);
        $this->_eventSerializer = new EventSerializer($options);
      
        $this->_capacity = $options['capacity'];
        $this->_timeout = $options['timeout'];
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
     * @param (int|mixed|string|true)[] $event
     */
    public function enqueue(array $event): bool
    {
        if (count($this->_queue) > $this->_capacity) {
            return false;
        }

        array_push($this->_queue, $event);

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
        $this->_queue = array();

        return $this->_eventPublisher->publish($payload);
    }

    /**
     * @psalm-suppress UndefinedClass
     */
    private function getEventPublisher(string $sdkKey, array $options): EventPublisher
    {
        $ep = $options['event_publisher'] ?? null;
        if (!$ep && isset($options['event_publisher_class'])) {
            $ep = $options['event_publisher_class'];
        }
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
