<?php

declare(strict_types=1);

namespace LaunchDarkly\Integrations;

/**
 * Integration with the curl HTTP client.
 * @since 3.5.0
 */
class Curl
{
    /**
     * Configures an adapter for sending analytics events to LaunchDarkly using curl.
     *
     * This is the default mechanism if you do not specify otherwise, so you should not need to call
     * this method explicitly; the options can be set in the main client configuration. However, if you
     * do choose to call this method, store its return value in the `event_publisher` property of the
     * client configuration.
     *
     *     $ep = LaunchDarkly\Integrations\Curl::eventPublisher();
     *     $config = [ "event_publisher" => $ep ];
     *     $client = new LDClient("sdk_key", $config);
     *
     * This implementation forks a process for each event payload. Alternatively, you can use
     * {@see \LaunchDarkly\Integrations\Guzzle::eventPublisher()}, which makes synchronous requests.
     *
     * @param array $options  Configuration settings (can also be passed in the main client configuration):
     *   - `curl`: command for executing `curl`; defaults to `/usr/bin/env curl`
     * @return mixed  an object to be stored in the `event_publisher` configuration property
     */
    public static function eventPublisher(array $options = []): mixed
    {
        return fn (string $sdkKey, array $baseOptions) =>
            new \LaunchDarkly\Impl\Integrations\CurlEventPublisher(
                $sdkKey,
                array_merge($baseOptions, $options)
            );
    }
}
