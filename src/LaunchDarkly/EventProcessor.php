<?php
namespace LaunchDarkly;

/**
 * @internal
 */
class EventProcessor
{
    private $_eventPublisher;
    private $_eventSerializer;
    private $_queue;
    private $_capacity;
    private $_timeout;

    public function __construct($sdkKey, $options = array()) {
      $this->_eventPublisher = $this->getEventPublisher($sdkKey, $options);
      $this->_eventSerializer = new EventSerializer($options);
      
      $this->_capacity = $options['capacity'];
      $this->_timeout = $options['timeout'];

      $this->_queue = array();
    }

    public function __destruct()
    {
        $this->flush();
    }

    public function sendEvent($event)
    {
        return $this->enqueue($event);
    }

    public function enqueue($event)
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
  public function flush()
  {
      if (empty($this->_queue)) {
          return null;
      }

      $payload = $this->_eventSerializer->serializeEvents($this->_queue);

      // We don't expect flush to be called more than once per request cycle, but let's empty the queue just in case
      $this->_queue = array();

      return $this->_eventPublisher->publish($payload);
  }

  /**
   * @param string $sdkKey
   * @param mixed[] $options
   * @return EventPublisher
   */
  private function getEventPublisher($sdkKey, array $options)
  {
      if (isset($options['event_publisher']) && $options['event_publisher'] instanceof EventPublisher) {
          return $options['event_publisher'];
      }

      if (isset($options['event_publisher_class'])) {
          $eventPublisherClass = $options['event_publisher_class'];
      } else {
          $eventPublisherClass = CurlEventPublisher::class;
      }

      if (!is_a($eventPublisherClass, EventPublisher::class, true)) {
          throw new \InvalidArgumentException;
      }
      return new $eventPublisherClass($sdkKey, $options);
  }
}
