<?php
namespace LaunchDarkly;

/**
 * Provides a transport mechanism for sending events to the LaunchDarkly service.
 */
interface EventPublisher {
    /**
     * @param string $sdkKey The SDK key for your account
     * @param mixed[] $options Client configuration settings
     */
    public function __construct($sdkKey, array $options);

    /**
     * Publish events to LaunchDarkly
     *
     * @param $payload string Event payload
     * @return bool Whether the events were successfully published
     */
    public function publish($payload);
}
