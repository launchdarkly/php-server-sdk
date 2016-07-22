<?php
namespace LaunchDarkly;

use GuzzleHttp\Client;
use \GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;

class GuzzleFeatureRequester implements FeatureRequester {

    private $_client;

    function __construct($baseUri, $apiKey, $options) {
        $stack = HandlerStack::create();
        $stack->push(new CacheMiddleware(), 'cache');
        $this->_client = new Client(array(
                                        'base_url' => $baseUri,
                                        'handler' => $stack,
                                        'defaults' => array(
                                            'headers' => array(
                                                'Authorization' => "api_key {$apiKey}",
                                                'Content-Type' => 'application/json',
                                                'User-Agent' => 'PHPClient/' . LDClient::VERSION
                                            ),
                                            'debug' => false,
                                            'timeout' => $options['timeout'],
                                            'connect_timeout' => $options['connect_timeout']
                                        )
                                    ));
    }


    /**
     * Gets feature data from a likely cached store
     *
     * @param $key string feature key
     * @return array|null The decoded JSON feature data, or null if missing
     */
    public function get($key) {
        try {
            $response = $this->_client->get("/api/eval/features/$key");
            return $response->json();
        } catch (BadResponseException $e) {
            $code = $e->getResponse()->getStatusCode();
            error_log("GuzzleFeatureRetriever::get received an unexpected HTTP status code $code");
            return null;
        }
    }
}