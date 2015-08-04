<?php
namespace LaunchDarkly;

use \GuzzleHttp\Exception\BadResponseException;
use \GuzzleHttp\Subscriber\Cache\CacheSubscriber;

/**
 * A client for the LaunchDarkly API.
 */
class LDClient {
    const DEFAULT_BASE_URI = 'https://app.launchdarkly.com';
    const VERSION = '0.4.1';

    protected $_apiKey;
    protected $_baseUri;
    protected $_client;
    protected $_eventProcessor;
    protected $_offline;

    /**
     * Creates a new client instance that connects to LaunchDarkly.
     *
     * @param string $apiKey  The API key for your account
     * @param array  $options Client configuration settings
     *     - base_uri: Base URI of the LaunchDarkly API. Defaults to `DEFAULT_BASE_URI`
     *     - timeout: Float describing the maximum length of a request in seconds. Defaults to 3
     *     - connect_timeout: Float describing the number of seconds to wait while trying to connect to a server. Defaults to 3
     *     - cache_storage: An optional GuzzleHttp\Subscriber\Cache\CacheStorageInterface. Defaults to an in-memory cache.
     */
    public function __construct($apiKey, $options = []) {
        $this->_apiKey = $apiKey;
        if (!isset($options['base_uri'])) {
            $this->_baseUri = self::DEFAULT_BASE_URI;
        } 
        else {
            $this->_baseUri = rtrim($options['base_uri'], '/');
        }
        if (!isset($options['timeout'])) {
            $options['timeout'] = 3;
        }
        if (!isset($options['connect_timeout'])) {
            $options['connect_timeout'] = 3;
        }

        if (!isset($options['capacity'])) {
            $options['capacity'] = 1000;
        }

        $this->_eventProcessor = new \LaunchDarkly\EventProcessor($apiKey, $options);

        $this->_client = $this->_make_client($options);
    }

    public function getFlag($key, $user, $default = false) {
        return $this->toggle($key, $user, $default);
    }

   /** 
    * Calculates the value of a feature flag for a given user.
    *
    * @param string  $key     The unique key for the feature flag
    * @param LDUser  $user    The end user requesting the flag
    * @param boolean $default The default value of the flag
    *
    * @return boolean Whether or not the flag should be enabled, or `default` if the flag is disabled in the LaunchDarkly control panel
    */
    public function toggle($key, $user, $default = false) {
        if ($this->_offline) {
            return $default;
        }

        try {
            $flag = $this->_toggle($key, $user, $default);

            if (is_null($flag)) {
                $this->_sendFlagRequestEvent($key, $user, $default);
                return $default;
            }
            else {
                $this->_sendFlagRequestEvent($key, $user, $flag);                
                return $flag;
            }
        } catch (\Exception $e) {
            error_log("LaunchDarkly caught $e");
            try {
                $this->_sendFlagRequestEvent($key, $user, $default);            
            }
            catch (\Exception $e) {
                error_log("LaunchDarkly caught $e");
            }
            return $default;
        }
    }

    /**
     * Puts the LaunchDarkly client in offline mode.
     * In offline mode, all calls to `toggle` will return the default value, and `track` will be a no-op.
     *
     */
    public function setOffline() {
        $this->_offline = true;
    }

    /**
     * Puts the LaunchDarkly client in online mode.
     *
     */
    public function setOnline() {
        $this->_offline = false;
    }

    /**
     * Returns whether the LaunchDarlkly client is in offline mode.
     *
     */
    public function isOffline() {
        return $this->_offline;
    }

    /**
     * Tracks that a user performed an event.
     *
     * @param string $eventName The name of the event
     * @param LDUser $user The user that performed the event
     *
     */
    public function track($eventName, $user, $data) {
        if ($this->isOffline()) {
            return;
        }

        $event = array();
        $event['user'] = $user->toJSON();
        $event['kind'] = "custom";
        $event['creationDate'] = round(microtime(1) * 1000);
        $event['key'] = $eventName;
        if (isset($data)) {
            $event['data'] = $data;
        }
        $this->_eventProcessor->enqueue($event);
    }

    public function identify($user) {
        if ($this->isOffline()) {
            return;
        }

        $event = array();
        $event['user'] = $user->toJSON();
        $event['kind'] = "identify";
        $event['creationDate'] = round(microtime(1) * 1000);
        $event['key'] = $user->getKey();
        $this->_eventProcessor->enqueue($event);        
    }

    protected function _sendFlagRequestEvent($key, $user, $value) {
        if ($this->isOffline()) {
            return;
        }

        $event = array();
        $event['user'] = $user->toJSON();
        $event['value'] = $value;
        $event['kind'] = "feature";
        $event['creationDate'] = round(microtime(1) * 1000);
        $event['key'] = $key;
        $this->_eventProcessor->enqueue($event); 
    }

    protected function _toggle($key, $user, $default) {
        try {
            $response = $this->_client->get("/api/eval/features/$key");
            return self::_decode($response->json(), $user);
        } catch (BadResponseException $e) {
            $code = $e->getResponse()->getStatusCode();
            error_log("LDClient::toggle received HTTP status code $code, using default");
            return $default;
        }
    }

    protected function _make_client($options) {
        $client = new \GuzzleHttp\Client([
            'base_url' => $this->_baseUri,
            'defaults' => [
                'headers' => [
                    'Authorization' => "api_key {$this->_apiKey}",
                    'Content-Type'  => 'application/json',
                    'User-Agent'    => 'PHPClient/' . self::VERSION
                ],
                'debug' => false,
                'timeout'         => $options['timeout'],
                'connect_timeout' => $options['connect_timeout']
            ]
        ]);

        if (!isset($options['cache_storage'])) {
            $csOptions = ['validate' => false];
        } else {
            $csOptions = ['storage' => $options['cache_storage'], 'validate' => false];
        }

        CacheSubscriber::attach($client, $csOptions);
        return $client;
    }

    protected static function _decode($json, $user) {
        $makeVariation = function ($v) {
            $makeTarget = function ($t) {
                return new TargetRule($t['attribute'], $t['op'], $t['values']);
            };

            $ts = empty($v['targets']) ? [] : $v['targets'];
            $targets = array_map($makeTarget, $ts);
            if (isset($v['userTarget'])) {
                return new Variation($v['value'], $v['weight'], $targets, $makeTarget($v['userTarget']));
            }
            else {
                return new Variation($v['value'], $v['weight'], $targets, null);
            }
        };

        $vs = empty($json['variations']) ? [] : $json['variations'];
        $variations = array_map($makeVariation, $vs);
        $feature = new FeatureRep($json['name'], $json['key'], $json['salt'], $json['on'], $variations);

        return $feature->evaluate($user);
    }
}
