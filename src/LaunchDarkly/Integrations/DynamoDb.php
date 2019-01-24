<?php

namespace LaunchDarkly\Integrations;

/**
 * Integration with a DynamoDB data store.
 * @since 3.5.0
 */
class DynamoDb
{
    /**
     * Configures an adapter for reading feature flag data from DynamoDB.
     *
     * To use this method, you must have installed the package `aws/aws-sdk-php`. After calling this
     * method, store its return value in the `feature_requester` property of your client configuration:
     *
     *     $fr = LaunchDarkly\Integrations\DynamoDb::newFeatureRequester([ "dynamodb_table" => "my-table" ]);
     *     $config = [ "feature_requester" => $fr ];
     *     $client = new LDClient("sdk_key", $config);
     *
     * For more about using LaunchDarkly with databases, see the
     * [SDK reference guide](https://docs.launchdarkly.com/v2.0/docs/using-a-persistent-feature-store).
     *
     * @param array $options  Configuration settings (can also be passed in the main client configuration):
     *   - `dynamodb_table`: (required) name of an existing table in DynamoDB.
     *   - `dynamodb_options`: can include any settings supported by the AWS SDK client
     *   - `dynamodb_prefix`: a string to be prepended to all database keys; corresponds to the prefix
     * setting in ld-relay
     *   - `apc_expiration`: expiration time in seconds for local caching, if `APCu` is installed
     * @return object  an object to be stored in the `feature_requester` configuration property
     */
    public static function newFeatureRequester($options = array())
    {
        return function ($baseUri, $sdkKey, $baseOptions) use ($options) {
            return new \LaunchDarkly\Impl\Integrations\DynamoDbFeatureRequester($baseUri, $sdkKey,
                array_merge($baseOptions, $options));
        };
    }
}
