<?php
namespace LaunchDarkly;

use Exception;

/**
 * A client for the LaunchDarkly API.
 */
class LDClient {
    const DEFAULT_BASE_URI = 'https://app.launchdarkly.com';
    const VERSION = '0.7.0';

    protected $_apiKey;
    protected $_baseUri;
    protected $_client;
    protected $_eventProcessor;
    protected $_offline;
    protected $_events = true;
    protected $_defaults = array();

    /** @var  FeatureRequester */
    protected $_featureRequester;

    /**
     * Creates a new client instance that connects to LaunchDarkly.
     *
     * @param string $apiKey  The API key for your account
     * @param array  $options Client configuration settings
     *     - base_uri: Base URI of the LaunchDarkly API. Defaults to `DEFAULT_BASE_URI`
     *     - timeout: Float describing the maximum length of a request in seconds. Defaults to 3
     *     - connect_timeout: Float describing the number of seconds to wait while trying to connect to a server. Defaults to 3
     */
    public function __construct($apiKey, $options = array()) {
        $this->_apiKey = $apiKey;
        if (!isset($options['base_uri'])) {
            $this->_baseUri = self::DEFAULT_BASE_URI;
        } 
        else {
            $this->_baseUri = rtrim($options['base_uri'], '/');
        }
        if (isset($options['events'])) {
            $this->_events = $options['events'];
        }
        if (isset($options['defaults'])) {
            $this->_defaults = $options['defaults'];
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

        $this->_eventProcessor = new EventProcessor($apiKey, $options);

        if (isset($options['feature_requester_class'])) {
            $featureRequesterClass = $options['feature_requester_class'];
        } else {
            $featureRequesterClass = '\\LaunchDarkly\\GuzzleFeatureRequester';
        }

        if (!is_a($featureRequesterClass, FeatureRequester::class, true)) {
            throw new \InvalidArgumentException;
        }
        $this->_featureRequester = new $featureRequesterClass($this->_baseUri, $apiKey, $options);
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
            $default = $this->_get_default($key, $default);
            $flag = $this->_toggle($key, $user);

            if (is_null($flag)) {
                $this->_sendFlagRequestEvent($key, $user, $default, $default);
                return $default;
            }
            else {
                $this->_sendFlagRequestEvent($key, $user, $flag, $default);                
                return $flag;
            }
        } catch (\Exception $e) {
            error_log("LaunchDarkly caught $e");
            try {
                $this->_sendFlagRequestEvent($key, $user, $default, $default);            
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
     * @param $eventName string The name of the event
     * @param $user LDUser The user that performed the event
     * @param $data mixed
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

    /**
     * @param $user LDUser
     */
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

    /**
     * @param $key string
     * @param $user LDUser
     * @param $value mixed
     */
    protected function _sendFlagRequestEvent($key, $user, $value, $default) {
        if ($this->isOffline() || !$this->_events) {
            return;
        }

        $event = array();
        $event['user'] = $user->toJSON();
        $event['value'] = $value;
        $event['kind'] = "feature";
        $event['creationDate'] = round(microtime(1) * 1000);
        $event['key'] = $key;
        $event['default'] = $default;
        $this->_eventProcessor->enqueue($event); 
    }

    protected function _toggle($key, $user) {
        try {
            $data = $this->_featureRequester->get($key);
            if ($data == null) {
                return null;
            }
            return self::_decode($data, $user);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            error_log("LDClient::_toggle received error $msg, using default");
            return null;
        }
    }

    protected function _get_default($key, $default) {
        if (array_key_exists($key, $this->_defaults)) {
            return $this->_defaults[$key];
        } else {
            return $default;
        }
    }

    protected static function _decode($json, $user) {
        $makeVariation = function ($v) {
            $makeTarget = function ($t) {
                return new TargetRule($t['attribute'], $t['op'], $t['values']);
            };

            $ts = empty($v['targets']) ? array() : $v['targets'];
            $targets = array_map($makeTarget, $ts);
            if (isset($v['userTarget'])) {
                return new Variation($v['value'], $v['weight'], $targets, $makeTarget($v['userTarget']));
            }
            else {
                return new Variation($v['value'], $v['weight'], $targets, null);
            }
        };

        $vs = empty($json['variations']) ? array() : $json['variations'];
        $variations = array_map($makeVariation, $vs);
        $feature = new FeatureRep($json['name'], $json['key'], $json['salt'], $json['on'], $variations);

        return $feature->evaluate($user);
    }
}
