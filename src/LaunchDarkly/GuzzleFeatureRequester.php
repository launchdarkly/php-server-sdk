<?php
namespace LaunchDarkly;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;

class GuzzleFeatureRequester implements FeatureRequester
{
    private $_client;
    private $_baseUri;
    private $_defaults;

    function __construct($baseUri, $apiKey, $options)
    {
        $this->_baseUri = $baseUri;
        error_log("uri: $baseUri");
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
        $this->_client = new Client(['handler' => $stack, 'debug' => false]);
    }


    /**
     * Gets feature data from a likely cached store
     *
     * @param $key string feature key
     * @return array|null The decoded JSON feature data, or null if missing
     */
    public function get($key)
    {
        try {
            $uri = $this->_baseUri . "/api/eval/features/$key";
            $response = $this->_client->get($uri, $this->_defaults);
            $body = $response->getBody();
            return json_decode($body, true);
        } catch (BadResponseException $e) {
            $code = $e->getResponse()->getStatusCode();
            error_log("GuzzleFeatureRetriever::get received an unexpected HTTP status code $code");
            return null;
        }
    }
}