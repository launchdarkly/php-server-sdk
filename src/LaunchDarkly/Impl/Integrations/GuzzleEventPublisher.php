<?php

namespace LaunchDarkly\Impl\Integrations;

use GuzzleHttp\Client;
use LaunchDarkly\EventPublisher;
use LaunchDarkly\Impl\UnrecoverableHTTPStatusException;
use LaunchDarkly\Impl\Util;
use LaunchDarkly\LDClient;
use Psr\Log\LoggerInterface;

/**
 * @ignore
 * @internal
 */
class GuzzleEventPublisher implements EventPublisher
{
    /** @var string */
    private $_sdkKey;
    /** @var string */
    private $_eventsUri;
    /** @var LoggerInterface */
    private $_logger;
    /** @var mixed[] */
    private $_requestOptions;

    public function __construct(string $sdkKey, array $options = [])
    {
        $this->_sdkKey = $sdkKey;
        $this->_logger = $options['logger'];
        if (isset($options['events_uri'])) {
            $this->_eventsUri = $options['events_uri'];
        } else {
            $this->_eventsUri = LDClient::DEFAULT_EVENTS_URI;
        }
        $this->_requestOptions = [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => $this->_sdkKey,
                'User-Agent'    => 'PHPClient/' . LDClient::VERSION,
                'Accept'        => 'application/json',
                'X-LaunchDarkly-Event-Schema' => strval(EventPublisher::CURRENT_SCHEMA_VERSION)
            ],
            'timeout' => $options['timeout'],
            'connect_timeout' => $options['connect_timeout']
        ];
    }

    public function publish(string $payload): bool
    {
        $client = new Client(['base_uri' => $this->_eventsUri]);
        $response = null;

        try {
            $options = $this->_requestOptions;
            $options['body'] = $payload;
            $response = $client->request('POST', '/bulk', $options);
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
