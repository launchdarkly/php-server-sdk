<?php
namespace LaunchDarkly;

use Doctrine\Common\Cache\ArrayCache;
use Guzzle\Http\Client;
use Guzzle\Cache\DoctrineCacheAdapter;
use Guzzle\Plugin\Cache\CachePlugin;

class GuzzleFeatureRequester implements FeatureRequester {
    function __construct($baseUri, $apiKey, $options) {
        $this->_client = new Client($baseUri,
                                        array(
                                        'debug' => false,
                                        'curl.options' => array('CURLOPT_TCP_NODELAY' => 1),
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

        if (isset($options['cache_storage'])) {
            $cachePlugin = new CachePlugin(array('storage' => $options['cache_storage'], 'validate' => false));
            $this->_client->addSubscriber($cachePlugin);
        }

    }


    /**
     * Gets feature data from a likely cached store
     *
     * @param $key string feature key
     * @return array|null The decoded JSON feature data, or null if missing
     */
    public function get($key) {
        try {
            $request = $this->_client->get("/api/eval/features/$key");
            $response = $request->send();
            return $response->json();
        } catch (BadResponseException $e) {
            $code = $e->getResponse()->getStatusCode();
            error_log("GuzzleFeatureRetriever::get received an unexpected HTTP status code $code");
            return null;
        }
    }
}