<?php
namespace LaunchDarkly;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use Psr\Log\LoggerInterface;

class GuzzleFeatureRequester implements FeatureRequester
{
    /** @var Client  */
    private $_client;
    /** @var string */
    private $_baseUri;
    /** @var array  */
    private $_defaults;
    /** @var  LoggerInterface */
    private $_logger;

    function __construct($baseUri, $apiKey, $options)
    {
        $this->_baseUri = $baseUri;
        $stack = HandlerStack::create();
        $stack->push(new CacheMiddleware(new PublicCacheStrategy(isset($options['cache']) ? $options['cache'] : null), 'cache'));

        $this->_defaults = array(
            'headers' => array(
                'Authorization' => "api_key {$apiKey}",
                'Content-Type' => 'application/json',
                'User-Agent' => 'PHPClient/' . LDClient::VERSION
            ),
            'timeout' => $options['timeout'],
            'connect_timeout' => $options['connect_timeout']
        );
        $this->_logger = $options['logger'];
        $this->_client = new Client(['handler' => $stack, 'debug' => false]);
    }


    /**
     * Gets feature data from a likely cached store
     *
     * @param $key string feature key
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function get($key)
    {
        try {
            $uri = $this->_baseUri . "/sdk/latest-flags/" . $key;
            $response = $this->_client->get($uri, $this->_defaults);
            $body = $response->getBody();
            return FeatureFlag::decode(json_decode($body, true));
        } catch (BadResponseException $e) {
            $code = $e->getResponse()->getStatusCode();
            $this->_logger->error("GuzzleFeatureRetriever::get received an unexpected HTTP status code $code");
            return null;
        }
    }
}