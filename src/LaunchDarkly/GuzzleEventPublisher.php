<?php
namespace LaunchDarkly;

use Guzzle\Http\Client;
use Psr\Log\LoggerInterface;

/**
 * Sends events to the LaunchDarkly service using the GuzzleHttp client.
 * This `EventPublisher` implement provides an in-process alternative to
 * the default `CurlEventPublisher` implementation which forks processes.
 *
 * Note that this implementation executes synchronously in the request
 * handler. In order to minimize request overhead, we recommend that you
 * set up `ld-relay` in your production environment and configure the
 * `events_uri` option for `LDClient` to publish to `ld-relay`.
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

    function __construct($sdkKey, array $options = array()) {
        $this->_sdkKey = $sdkKey;
        $this->_logger = $options['logger'];
        if (isset($options['events_uri'])) {
            $this->_eventsUri = $options['events_uri'];
        } else {
            $this->_eventsUri = LDClient::DEFAULT_EVENTS_URI;
        }
        $this->_requestOptions = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => $this->_sdkKey,
                'User-Agent'    => 'PHPClient/' . LDClient::VERSION,
                'Accept'        => 'application/json'
            ),
            'timeout' => $options['timeout'],
            'connect_timeout' => $options['connect_timeout']
        );
    }

    public function publish($payload) {
        $client = new Client($this->_eventsUri);

        try {
            $options = $this->_requestOptions;
            $options['body'] = $payload;
            $response = $client->request('POST', '/bulk', $options);

            return $response->getStatusCode() < 300;
        } catch (\Exception $e) {
            $this->_logger->warning("GuzzleEventPublisher::publish caught $e");
            return false;
        }
    }
}