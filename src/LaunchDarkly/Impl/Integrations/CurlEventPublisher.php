<?php
namespace LaunchDarkly\Impl\Integrations;

use LaunchDarkly\EventPublisher;
use LaunchDarkly\LDClient;

/**
 * Curl-based implementation of sending events. This is used by default.
 *
 * @ignore
 * @internal
 */
class CurlEventPublisher implements EventPublisher
{
    /** @var string */
    private $_sdkKey;

    /** @var string */
    private $_host;

    /** @var int */
    private $_port;

    /** @var string */
    private $_path;

    /** @var bool */
    private $_ssl;

    /** @var string */
    private $_curl = '/usr/bin/env curl';

    /** @var int */
    private $_connectTimeout;

    public function __construct(string $sdkKey, array $options = array())
    {
        $this->_sdkKey = $sdkKey;

        $eventsUri = LDClient::DEFAULT_EVENTS_URI;
        if (isset($options['events_uri'])) {
            $eventsUri = $options['events_uri'];
        }
        $url = parse_url(rtrim($eventsUri, '/'));
        $this->_host = $url['host'] ?? '';
        $this->_ssl = ($url['scheme'] ?? '') === 'https';
        if (isset($url['port'])) {
            $this->_port = $url['port'];
        } else {
            $this->_port = $this->_ssl ? 443 : 80;
        }
        $this->_path = $url['path'] ?? '';

        if (array_key_exists('curl', $options)) {
            $this->_curl = $options['curl'];
        }

        $this->_connectTimeout = $options['connect_timeout'];
    }

    public function publish(string $payload): bool
    {
        $args = $this->createArgs($payload);

        return $this->makeRequest($args);
    }

    private function createArgs(string $payload): string
    {
        $scheme = $this->_ssl ? "https://" : "http://";
        $args = " -X POST";
        $args.= " --connect-timeout " . $this->_connectTimeout;
        $args.= " -H 'Content-Type: application/json'";
        $args.= " -H " . escapeshellarg("Authorization: " . $this->_sdkKey);
        $args.= " -H 'User-Agent: PHPClient/" . LDClient::VERSION . "'";
        $args.= " -H 'X-LaunchDarkly-Event-Schema: " . EventPublisher::CURRENT_SCHEMA_VERSION . "'";
        $args.= " -H 'Accept: application/json'";
        $args.= " -d " . escapeshellarg($payload);
        $args.= " " . escapeshellarg($scheme . $this->_host . ":" . $this->_port . $this->_path . "/bulk");
        return $args;
    }

    /**
     * @psalm-suppress ForbiddenCode
     */
    private function makeRequest(string $args): bool
    {
        $cmd = $this->_curl . " " . $args . ">> /dev/null 2>&1 &";
        shell_exec($cmd);
        return true;
    }
}
