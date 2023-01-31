<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Integrations;

use GuzzleHttp\Client;
use LaunchDarkly\Impl\UnrecoverableHTTPStatusException;
use LaunchDarkly\Impl\Util;
use LaunchDarkly\LDClient;
use LaunchDarkly\Subsystems\EventPublisher;
use Psr\Log\LoggerInterface;

/**
 * @ignore
 * @internal
 */
class GuzzleEventPublisher implements EventPublisher
{
    private string $_sdkKey;
    private string $_eventsUri;
    private LoggerInterface $_logger;
    private array $_requestOptions;

    public function __construct(string $sdkKey, array $options = [])
    {
        $this->_sdkKey = $sdkKey;
        $this->_logger = $options['logger'];

        $baseUri = $options['events_uri'] ?? null;
        if (!$baseUri) {
            $baseUri = LDClient::DEFAULT_EVENTS_URI;
        }
        $this->_eventsUri = \LaunchDarkly\Impl\Util::adjustBaseUri($baseUri);

        $this->_requestOptions = [
            'headers' => Util::eventHeaders($this->_sdkKey, $options['application_info'] ?? null),
            'timeout' => $options['timeout'],
            'connect_timeout' => $options['connect_timeout']
        ];
    }

    public function publish(string $payload): bool
    {
        $client = new Client(['base_uri' => $this->_eventsUri]);

        try {
            $options = $this->_requestOptions;
            $options['body'] = $payload;
            $response = $client->request('POST', 'bulk', $options);
        } catch (\Exception $e) {
            $this->_logger->warning("GuzzleEventPublisher::publish caught $e");
            return false;
        }
        if ($response->getStatusCode() >= 300) {
            $this->_logger->error(Util::httpErrorMessage($response->getStatusCode(), 'event posting', 'some events were dropped'));
            if (!Util::isHttpErrorRecoverable($response->getStatusCode())) {
                throw new UnrecoverableHTTPStatusException($response->getStatusCode());
            }
            return false;
        }
        return true;
    }
}
