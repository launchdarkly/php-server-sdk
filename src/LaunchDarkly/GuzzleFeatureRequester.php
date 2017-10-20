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
    /** @var LoggerInterface */
    private $_logger;
    /** @var boolean */
    private $_loggedCacheNotice = FALSE;
    /** @var boolean */
    private $_stopped = FALSE;

    function __construct($baseUri, $sdkKey, $options)
    {
        $this->_baseUri = $baseUri;
        $this->_logger = $options['logger'];
        $stack = HandlerStack::create();
        if (class_exists('Kevinrob\GuzzleCache\CacheMiddleware')) {
            $stack->push(new CacheMiddleware(new PublicCacheStrategy(isset($options['cache']) ? $options['cache'] : null), 'cache'));
        } else if (!$this->_loggedCacheNotice) {
            $this->_logger->info("GuzzleFeatureRequester is not using an HTTP cache because Kevinrob\GuzzleCache\CacheMiddleware was not installed");
            $this->_loggedCacheNotice = TRUE;
        }

        $this->_defaults = array(
            'headers' => array(
                'Authorization' => $sdkKey,
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
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function get($key)
    {
        if ($this->_stopped) {
            return null;
        }
        try {
            $uri = $this->_baseUri . self::SDK_FLAGS . "/" . $key;
            $response = $this->_client->get($uri, $this->_defaults);
            $body = $response->getBody();
            return FeatureFlag::decode(json_decode($body, true));
        } catch (BadResponseException $e) {
            $code = $e->getResponse()->getStatusCode();
            if ($code == 404) {
                $this->_logger->warning("GuzzleFeatureRequester::get returned 404. Feature flag does not exist for key: " . $key);
            } else {
                $this->handleUnexpectedStatus($code, "GuzzleFeatureRequester::get");
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
        if ($this->_stopped) {
            return null;
        }
        try {
            $uri = $this->_baseUri . self::SDK_FLAGS;
            $response = $this->_client->get($uri, $this->_defaults);
            $body = $response->getBody();
            return array_map(FeatureFlag::getDecoder(), json_decode($body, true));
        } catch (BadResponseException $e) {
            $this->handleUnexpectedStatus($e->getResponse()->getStatusCode(), "GuzzleFeatureRequester::getAll");
            return null;
        }
    }

    private function handleUnexpectedStatus($code, $method) {
        $this->_logger->error("$method received an unexpected HTTP status code $code");
        if ($code == 401) {
            $this->_logger->error("Received 401 error, no further feature requests will be made during lifetime of LD client since SDK key is invalid");
            $this->stopped = TRUE;
        }
    }
}