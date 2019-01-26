<?php
namespace LaunchDarkly;

/**
 * Deprecated implementation class for using GuzzleHttp to connect to LaunchDarkly.
 * Replaced by {@link \LaunchDarkly\Integrations\Guzzle::featureRequester()}.
 *
 * @deprecated Use {@link \LaunchDarkly\Integrations\Guzzle::featureRequester()}
 */
class GuzzleFeatureRequester extends \LaunchDarkly\Impl\Integrations\GuzzleFeatureRequester
{
}
