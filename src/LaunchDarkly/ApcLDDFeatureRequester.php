<?php
namespace LaunchDarkly;


/**
 * Feature requester from an LDD-populated redis, with APC caching
 *
 * @package LaunchDarkly
 */
class ApcLDDFeatureRequester extends LDDFeatureRequester {
    protected $_expiration = 30;

    function __construct($baseUri, $apiKey, $options) {
        parent::__construct($baseUri, $apiKey, $options);

        if (isset($options['apc_expiration'])) {
            $this->_expiration = (int)$options['apc_expiration'];
        }
    }


    protected function get_from_cache($key) {
        $key = self::make_cache_key($key);
        $enabled = apc_fetch($key);
        if ($enabled === false) {
            return null;
        }
        else {
            return $enabled;
        }
    }

    protected function store_in_cache($key, $val) {
        apc_add($this->make_cache_key($key), $val, $this->_expiration);
    }

    private function make_cache_key($name) {
        return $this->_features_key.'.'.$name;
    }
}