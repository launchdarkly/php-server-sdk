<?php

declare(strict_types=1);

namespace LaunchDarkly\Integrations;

/**
 * Integration with the Guzzle HTTP client.
 * @since 3.5.0
 */
class Guzzle
{
    /**
     * Configures an adapter for requesting feature flag data from LaunchDarkly using GuzzleHttp.
     *
     * This is the default mechanism if you do not specify otherwise, so you should not need to call
     * this method explicitly; the options can be set in the main client configuration. However, if you
     * do choose to call this method, store its return value in the `feature_requester` property of the
     * client configuration.
     *
     * @param array $options  Configuration settings (can also be passed in the main client configuration):
     *   - `cache`: an optional object that implements `Kevinrob\GuzzleCache\Storage\CacheStorageInterface`
     *   - `connect_timeout`: connection timeout in seconds; defaults to 3
     *   - `timeout`: read timeout in seconds; defaults to 3
     * @return mixed  an object to be stored in the `feature_requester` configuration property
     */
    public static function featureRequester(array $options = []): mixed
    {
        return fn (string $baseUri, string $sdkKey, array $baseOptions) =>
            new \LaunchDarkly\Impl\Integrations\GuzzleFeatureRequester(
                $baseUri,
                $sdkKey,
                array_merge($baseOptions, $options)
            );
    }

    /**
     * Configures an adapter for sending analytics events to LaunchDarkly using GuzzleHttp.
     *
     * The default mechanism for sending events is {@see \LaunchDarkly\Integrations\Curl::eventPublisher()}.
     * To use Guzzle instead, call this method and store its return value in the `event_publisher` property
     * of the client configuration:
     *
     *     $ep = LaunchDarkly\Integrations\Guzzle::eventPublisher();
     *     $config = [ "event_publisher" => $ep ];
     *     $client = new LDClient("sdk_key", $config);
     *
     * Unlike the curl implementation, which forks processes, this implementation executes synchronously in
     * the request handler. In order to minimize request overhead, we recommend that you set up `ld-relay`
     * in your production environment and configure the `events_uri` option for `LDClient` to publish to
     * `ld-relay`.
     *
     * @param array $options  Configuration settings (can also be passed in the main client configuration):
     *   - `events_uri`: URI of the server that will receive events, if it is `ld-relay` instead of LaunchDarkly
     *   - `connect_timeout`: connection timeout in seconds; defaults to 3
     *   - `timeout`: read timeout in seconds; defaults to 3
     * @return mixed  an object to be stored in the `event_publisher` configuration property
     */
    public static function eventPublisher(array $options = []): mixed
    {
        return fn (string $sdkKey, array $baseOptions) =>
            new \LaunchDarkly\Impl\Integrations\GuzzleEventPublisher(
                $sdkKey,
                array_merge($baseOptions, $options)
            );
    }
}
