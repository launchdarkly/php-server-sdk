<?php
namespace LaunchDarkly;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Plugin\Cache\CachePlugin;
use Psr\Log\LoggerInterface;

class GuzzleFeatureRequester implements FeatureRequester {
    const SDK_FLAGS = "/sdk/flags";
    /** @var Client  */
    private $_client;
    /** @var  LoggerInterface */
    private $_logger;

    function __construct($baseUri, $sdkKey, $options) {
        $this->_client = new Client($baseUri,
                                        array(
                                        'debug' => false,
                                        'curl.options' => array('CURLOPT_TCP_NODELAY' => 1),
                                        'request.options' => array(
                                            'headers' => array(
                                                'Authorization' => "{$sdkKey}",
                                                'Content-Type' => 'application/json'
                                            ),
                                            'timeout' => $options['timeout'],
                                            'connect_timeout' => $options['connect_timeout']
                                        )
                                    ));
        $this->_client->setUserAgent('PHPClient53/' . LDClient::VERSION);
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
    public function get($key)
    {
        try {
            $uri = self::SDK_FLAGS . "/" . $key;
            $response = $this->_client->get($uri)->send();
            $body = $response->getBody();
            return FeatureFlag::decode(json_decode($body, true));
        } catch (BadResponseException $e) {
            $code = $e->getResponse()->getStatusCode();
            $this->_logger->error("GuzzleFeatureRetriever::get received an unexpected HTTP status code $code");
            return null;
        }
    }

    /**
     * Gets all features from a likely cached store
     *
     * @return array()|null The decoded FeatureFlags, or null if missing
     */
    public function getAll() {
        try {
            $uri = self::SDK_FLAGS;
            $response = $this->_client->get($uri)->send();
            $body = $response->getBody();
            return array_map(FeatureFlag::getDecoder(), json_decode($body, true));
        } catch (BadResponseException $e) {
            $code = $e->getResponse()->getStatusCode();
            $this->_logger->error("GuzzleFeatureRetriever::getAll received an unexpected HTTP status code $code");
            return null;
        }
    }    
}