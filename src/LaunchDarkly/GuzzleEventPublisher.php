<?php
namespace LaunchDarkly;

/**
 * Deprecated integration class for using GuzzleHttp to send analytics events.
 *
 * Replaced by {@link \LaunchDarkly\Integrations\Guzzle::eventPublisher()}.
 *
 * @deprecated Use \LaunchDarkly\Integrations\Guzzle::eventPublisher()
 */
class GuzzleEventPublisher extends \LaunchDarkly\Impl\Integrations\GuzzleEventPublisher
{
}
