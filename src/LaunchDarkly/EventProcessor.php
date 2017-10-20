<?php
namespace LaunchDarkly;

/**
 * @internal
 */
class EventProcessor {

  private $_eventPublisher;
  private $_queue;
  private $_capacity;
  private $_timeout;
  private $_logger;
  private $_stopped = FALSE;

  public function __construct($sdkKey, $options = array()) {
    $this->_eventPublisher = $this->getEventPublisher($sdkKey, $options);

    $this->_capacity = $options['capacity'];
    $this->_timeout = $options['timeout'];
    $this->_logger = $options['logger'];

    $this->_queue = array();
  }

  public function __destruct() {
    $this->flush();
  }

  public function sendEvent($event) {
    return $this->enqueue($event);
  }

  public function enqueue($event) {
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
  public function flush() {
    if ($this->_stopped || empty($this->_queue)) {
      return null;
    }

    $payload = json_encode($this->_queue);

    $this->queue = array();

    try {
      $this->_eventPublisher->publish($payload);
    } catch (InvalidSDKException $e) {
      $this->_logger->error("Received 401 error, no further events will be posted during lifetime of LD client since SDK key is invalid");
      $this->_stopped = TRUE;
    }
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