<?php
namespace LaunchDarkly;

/**
 * Deprecated integration class for using GuzzleHttp to request flags from LaunchDarkly.
 *
 * Replaced by {@link \LaunchDarkly\Integrations\Guzzle::featureRequester()}.
 *
 * @deprecated Use \LaunchDarkly\Integrations\Guzzle::featureRequester()
 */
class GuzzleFeatureRequester extends \LaunchDarkly\Impl\Integrations\GuzzleFeatureRequester
{
}
