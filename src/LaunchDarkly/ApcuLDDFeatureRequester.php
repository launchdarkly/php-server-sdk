<?php

namespace LaunchDarkly;

/**
 * Feature requester from an LDD-populated redis, with APCu caching.
 *
 * Unlike APC, APCu is actively maintained and is available from php53 to php7.
 *
 * This class is deprecated. Use {@link \LaunchDarkly\Integrations\Redis::newFeatureRequester()}
 * and set the `apc_expiration` option.
 *
 * @deprecated Use {@link \LaunchDarkly\Integrations\Redis::newFeatureRequester()} and set the `apc_expiration` option.
 *
 * @package LaunchDarkly
 */
class ApcuLDDFeatureRequester extends LDDFeatureRequester
{
    public function __construct($baseUri, $sdkKey, $options)
    {
        if (!isset($options['apc_expiration'])) {
            $options['apc_expiration'] = 30;
        }
        parent::__construct($baseUri, $sdkKey, $options);
    }
}
