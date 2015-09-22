<?php
namespace LaunchDarkly;

use GuzzleHttp\Client;
use \GuzzleHttp\Exception\BadResponseException;
use \GuzzleHttp\Subscriber\Cache\CacheSubscriber;

class GuzzleFeatureRequester implements FeatureRequester {
    function __construct($baseUri, $apiKey, $options) {
        $this->_client = new Client(array(
                                        'base_url' => $baseUri,
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

        if (!isset($options['cache_storage'])) {
            $csOptions = array('validate' => false);
        }
        else {
            $csOptions = array('storage' => $options['cache_storage'], 'validate' => false);
        }

        CacheSubscriber::attach($this->_client, $csOptions);
    }


    /**
     * Gets feature data from a likely cached store
     *
     * @param $key string feature key
     * @return mixed The decoded JSON feature data, or null if missing
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