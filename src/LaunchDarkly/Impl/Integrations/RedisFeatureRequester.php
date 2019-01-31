<?php
namespace LaunchDarkly\Impl\Integrations;

use Predis\ClientInterface;
use Psr\Log\LoggerInterface;

class RedisFeatureRequester extends FeatureRequesterBase
{
    /** @var array */
    private $_redisOptions;
    /** @var ClientInterface */
    private $_connection;
    /** @var string */
    private $_prefix;

    public function __construct($baseUri, $sdkKey, $options)
    {
        parent::__construct($baseUri, $sdkKey, $options);

        $this->_prefix = isset($options['redis_prefix']) ? $options['redis_prefix'] : 'launchdarkly';

        if (isset($this->_options['predis_client']) && $this->_options['predis_client'] instanceof ClientInterface) {
            $this->_connection = $this->_options['predis_client'];
        } else {
            $this->_redisOptions = array(
                "scheme" => "tcp",
                "timeout" => isset($options['redis_timeout']) ? $options['redis_timeout'] : 5,
                "host" => isset($options['redis_host']) ? $options['redis_host'] : 'localhost',
                "port" => isset($options['redis_port']) ? $options['redis_port'] : 6379
            );
        }
    }

    protected function readItemString($namespace, $key)
    {
        $redis = $this->getConnection();
        return $redis->hget("$this->_prefix:$namespace", $key);
    }

    protected function readItemStringList($namespace)
    {
        $redis = $this->getConnection();
        $raw = $redis->hgetall("$this->_prefix:$namespace");
        return $raw ? array_values($raw) : null;
    }

    /**
     * @return ClientInterface
     */
    protected function getConnection()
    {
        if ($this->_connection instanceof ClientInterface) {
            return $this->_connection;
        }

        /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        return $this->_connection = new \Predis\Client($this->_redisOptions);
    }
}
