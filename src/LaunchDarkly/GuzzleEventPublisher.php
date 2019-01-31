<?php
namespace LaunchDarkly;

/**
 * Deprecated implementation class for using GuzzleHttp to send analytics events.
 * Replaced by {@link \LaunchDarkly\Integrations\Guzzle::eventPublisher()}.
 *
 * @deprecated Use {@link \LaunchDarkly\Integrations\Guzzle::eventPublisher()}
 */
class GuzzleEventPublisher extends \LaunchDarkly\Impl\Integrations\GuzzleEventPublisher
{
}
