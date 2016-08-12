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
    const SDK_FLAGS = "/sdk/flags";
    /** @var Client  */
    private $_client;
    /** @var string */
    private $_baseUri;
    /** @var array  */
    private $_defaults;
    /** @var  LoggerInterface */
    private $_logger;

    function __construct($baseUri, $sdkKey, $options)
    {
        $this->_baseUri = $baseUri;
        $stack = HandlerStack::create();
        $stack->push(new CacheMiddleware(new PublicCacheStrategy(isset($options['cache']) ? $options['cache'] : null), 'cache'));

        $this->_defaults = array(
            'headers' => array(
                'Authorization' => $sdkKey,
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
            $uri = $this->_baseUri . self::SDK_FLAGS . "/" . $key;
            $response = $this->_client->get($uri, $this->_defaults);
            $body = $response->getBody();
            return FeatureFlag::decode(json_decode($body, true));
        } catch (BadResponseException $e) {
            $code = $e->getResponse()->getStatusCode();
            if ($code == 404) {
                $this->_logger->warning("GuzzleFeatureRetriever::get returned 404. Feature flag does not exist for key: " . $key);
            } else {
                $this->_logger->error("GuzzleFeatureRetriever::get received an unexpected HTTP status code $code");
            }
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