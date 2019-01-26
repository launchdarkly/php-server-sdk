<?php
namespace LaunchDarkly;

/**
 * Deprecated implementation class for using curl to send analytics events.
 * Replaced by {@link \LaunchDarkly\Integrations\Curl::eventPublisher()}.
 *
 * @deprecated Use {@link \LaunchDarkly\Integrations\Curl::eventPublisher()}
 */
class CurlEventPublisher extends \LaunchDarkly\Impl\Integrations\CurlEventPublisher
{
}
