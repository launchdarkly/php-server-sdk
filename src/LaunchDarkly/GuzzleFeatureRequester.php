<?php
namespace LaunchDarkly;

use Guzzle\Http\Client;
use Guzzle\Plugin\Cache;

class GuzzleFeatureRequester implements FeatureRequester {
    function __construct($baseUri, $apiKey, $options) {
        $this->_client = new Client($baseUri,
                                        array(
                                        'plugins' => array(new Guzzle\Plugin\Cache\CachePlugin()),                                        
                                        'debug' => false,
                                        'request.options' => array(
                                            'headers' => array(
                                                'Authorization' => "api_key {$apiKey}",
                                                'Content-Type' => 'application/json'
                                            ),
                                            'timeout' => $options['timeout'],
                                            'connect_timeout' => $options['connect_timeout']
                                        )
                                    ));
        $this->_client->setUserAgent('PHPClient/' . LDClient::VERSION);
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