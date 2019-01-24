<?php
namespace LaunchDarkly;

/**
 * Deprecated implementation class for Redis integration.
 * Replaced by {@link \LaunchDarkly\Integrations\Redis::newFeatureRequester()}.
 *
 * @deprecated Use {@link \LaunchDarkly\Integrations\Redis::newFeatureRequester()}
 */
class LDDFeatureRequester extends \LaunchDarkly\Impl\Integrations\RedisFeatureRequester
{
}
