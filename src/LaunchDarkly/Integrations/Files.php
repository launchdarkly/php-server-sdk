<?php

declare(strict_types=1);

namespace LaunchDarkly\Integrations;

/**
 * Integration with filesystem data.
 * @since 3.5.0
 */
class Files
{
    /**
     * This component allows you to use local files as a source of feature flag state.
     *
     * This would typically be used in a test environment, to operate using a predetermined feature flag state
     * without an actual LaunchDarkly connection.
     *
     * To use this component, create an instance of this class, passing the path(s) of your data
     * file(s). Then place the resulting object in your LaunchDarkly client configuration with the
     * key `feature_requester`.
     *
     *     $fr = LaunchDarkly\Integrations\Files::featureRequester("./testData/flags.json");
     *     $config = [ "feature_requester" => $fr, "send_events" => false ];
     *     $client = new LDClient("sdk_key", $config);
     *
     * This will cause the client _not_ to connect to LaunchDarkly to get feature flags. (Note
     * that in this example, `send_events` is also set to false so that it will not connect to
     * LaunchDarkly to send analytics events either.)
     *
     * For more information about using this component, and the format of data files, see
     * the SDK reference guide on ["Reading flags from a file"](https://docs.launchdarkly.com/sdk/features/flags-from-files#php).
     *
     * @param string|string[] $filePaths relative or absolute paths to the data files
     * @return mixed  an object to be stored in the `feature_requester` configuration property
     */
    public static function featureRequester(string|array $filePaths): mixed
    {
        return new \LaunchDarkly\Impl\Integrations\FileDataFeatureRequester($filePaths);
    }
}
