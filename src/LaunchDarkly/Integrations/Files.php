<?php

namespace LaunchDarkly\Integrations;

/**
 * Integration with filesystem data.
 * @since 3.5.0
 */
class Files {
    /**
     * This component allows you to use local files as a source of feature flag state. This would
     * typically be used in a test environment, to operate using a predetermined feature flag state
     * without an actual LaunchDarkly connection.
     * 
     * To use this component, create an instance of this class, passing the path(s) of your data
     * file(s). Then place the resulting object in your LaunchDarkly client configuration with the
     * key `feature_requester`.
     * 
     *     $fr = LaunchDarkly\Integrations\Files::newFeatureRequester("./testData/flags.json");
     *     $config = [ "feature_requester" => $fr, "send_events" => false ];
     *     $client = new LDClient("sdk_key", $config);
     * 
     * This will cause the client _not_ to connect to LaunchDarkly to get feature flags. (Note
     * that in this example, `send_events` is also set to false so that it will not connect to
     * LaunchDarkly to send analytics events either.)
     *
     * @param array $filePaths relative or absolute paths to the data files
     * @return 
     */
    public static function newFeatureRequester($filePaths) {
        return new \LaunchDarkly\Impl\Integrations\FileDataFeatureRequester($filePaths);
    }
}
