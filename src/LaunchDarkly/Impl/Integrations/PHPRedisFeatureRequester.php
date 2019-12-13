<?php
namespace LaunchDarkly\Impl\Integrations;

use Psr\Log\LoggerInterface;

class PHPRedisFeatureRequester extends FeatureRequesterBase
{
    /** @var array */
    private $_redisOptions;
    /** @var \Redis */
    private $_redisInstance;
    /** @var string */
    private $_prefix;

    public function __construct($baseUri, $sdkKey, $options)
    {
        parent::__construct($baseUri, $sdkKey, $options);

        $this->_prefix = isset($options['redis_prefix']) ? $options['redis_prefix'] : 'launchdarkly';

        if (isset($this->_options['phpredis_client']) && $this->_options['phpredis_client'] instanceof Redis) {
            $this->_connection = $this->_options['phpredis_client'];
        } else {
            $this->_redisOptions = array(
                "timeout" => isset($options['redis_timeout']) ? $options['redis_timeout'] : 5,
                "host" => isset($options['redis_host']) ? $options['redis_host'] : 'localhost',
                "port" => isset($options['redis_port']) ? $options['redis_port'] : 6379
            );
        }
    }

    protected function readItemString($namespace, $key)
    {
        $redis = $this->getConnection();
        return $redis->hget($namespace, $key);
    }

    protected function readItemStringList($namespace)
    {
        $redis = $this->getConnection();
        $raw = $redis->hgetall($namespace);
        return $raw ? array_values($raw) : null;
    }

    /**
     * @return \Redis
     */
    protected function getConnection()
    {
        if ($this->_redisInstance instanceof Redis) {
            return $this->_redisInstance;
        }

        $redis = new \Redis();
        $redis->pconnect(
            $this->_redisOptions["host"],
            $this->_redisOptions["port"],
            $this->_redisOptions["timeout"],
            'x'
        );
        $redis->setOption(\Redis::OPT_PREFIX, "$this->_prefix:");	// use custom prefix on all keys
        return $this->_redisInstance = $redis;
    }
}
