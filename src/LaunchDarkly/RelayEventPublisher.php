<?php
namespace LaunchDarkly;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class RelayEventPublisher implements EventPublisher
{
    /** @var string */
    private $_sdkKey;
    /** @var string */
    private $_eventsUri;
    /** @var LoggerInterface */
    private $_logger;
    /** @var mixed[] */
    private $_requestOptions;

    function __construct($sdkKey, array $options = array()) {
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
                'Accept'        => 'application/json'
            ],
            'timeout' => $options['timeout'],
            'connect_timeout' => $options['connect_timeout']
        ];
    }

    public function publish($payload) {
        $client = new Client(['base_uri' => $this->_eventsUri]);

        try {
            $options = $this->_requestOptions;
            $options['body'] = $payload;
            $response = $client->request('POST', '/bulk', $options);

            return $response->getStatusCode() < 300;
        } catch (\Exception $e) {
            $this->_logger->warning("RelayEventPublisher::publish caught $e");
            return false;
        }
    }
}