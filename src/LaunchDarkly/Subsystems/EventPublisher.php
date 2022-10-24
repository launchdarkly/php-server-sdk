<?php

declare(strict_types=1);

namespace LaunchDarkly\Subsystems;

/**
 * Interface for the component that sends events to the LaunchDarkly service.
 *
 * Application code should not need to implement this interface. LaunchDarkly provides two implementations:
 *
 * - {@see \LaunchDarkly\Integrations\Curl::eventPublisher()} (the default)
 * - {@see \LaunchDarkly\Integrations\Guzzle::eventPublisher()}
 */
interface EventPublisher
{
    /**
     * @var int
     * @ignore
     */
    const CURRENT_SCHEMA_VERSION = 2;

    /**
     * @param string $sdkKey The SDK key for your account
     * @param mixed[] $options Client configuration settings
     */
    public function __construct(string $sdkKey, array $options);

    /**
     * Publishes events to LaunchDarkly.
     *
     * @param string $payload Event payload
     * @return bool Whether the events were successfully published
     */
    public function publish(string $payload): bool;
}
