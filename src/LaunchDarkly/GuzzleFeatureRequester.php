<?php
namespace LaunchDarkly;

use GuzzleHttp\Client;
use \GuzzleHttp\Exception\BadResponseException;
use \GuzzleHttp\Subscriber\Cache\CacheSubscriber;
use Psr\Log\LoggerInterface;

class GuzzleFeatureRequester implements FeatureRequester {
    const SDK_FLAGS = "/sdk/flags";
    /** @var Client  */
    private $_client;
    /** @var  LoggerInterface */
    private $_logger;

    function __construct($baseUri, $sdkKey, $options) {
        $this->_client = new Client(array(
                                        'base_url' => $baseUri,
                                        'defaults' => array(
                                            'headers' => array(
                                                'Authorization' => "{$sdkKey}",
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
        $this->_logger = $options['logger'];
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
            $response = $this->_client->get($uri, $this->_defaults);
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
            $uri = $this->_baseUri . self::SDK_FLAGS;
            $response = $this->_client->get($uri, $this->_defaults);
            $body = $response->getBody();
            return array_map(FeatureFlag::getDecoder(), json_decode($body, true));
        } catch (BadResponseException $e) {
            $code = $e->getResponse()->getStatusCode();
            $this->_logger->error("GuzzleFeatureRetriever::getAll received an unexpected HTTP status code $code");
            return null;
        }
    }    
}