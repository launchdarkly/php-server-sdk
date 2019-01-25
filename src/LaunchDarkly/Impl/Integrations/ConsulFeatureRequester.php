<?php
namespace LaunchDarkly\Impl\Integrations;

use SensioLabs\Consul\Exception\ClientException;
use SensioLabs\Consul\ServiceFactory;

class ConsulFeatureRequester extends FeatureRequesterBase
{
    /** @var string */
    protected $_prefix;
    /** @var \SensioLabs\Consul\Services\KV */
    protected $_kvClient;

    public function __construct($baseUri, $sdkKey, $options)
    {
        parent::__construct($baseUri, $sdkKey, $options);

        $consulOpts = isset($options['consul_options']) ? $options['consul_options'] : array();
        if (isset($options['consul_uri'])) {
            $consulOpts['base_uri'] = $options['consul_uri'];
        }
        $sf = new ServiceFactory($consulOpts);
        $this->_kvClient = $sf->get('kv');

        $prefix = isset($options['consul_prefix']) ? $options['consul_prefix'] : 'launchdarkly';
        $this->_prefix = $prefix . '/';
    }

    protected function readItemString($namespace, $key)
    {
        try {
            $resp = $this->_kvClient->get($this->makeKey($namespace, $key));
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
        $results = $resp->json();
        if (count($results) != 1) {
            return null;
        }
        return base64_decode($results[0]['Value']);
    }

    protected function readItemStringList($namespace)
    {
        try {
            $resp = $this->_kvClient->get($this->makeKey($namespace, ''), array('recurse' => true));
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                return array();
            }
            throw $e;
        }
        $results = $resp->json();
        $ret = array();
        foreach ($results as $result) {
            $ret[] = base64_decode($result['Value']);
        }
        return $ret;
    }

    private function makeKey($namespace, $key)
    {
        return $this->_prefix . $namespace . '/' . $key;
    }
}
