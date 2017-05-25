<?php
namespace LaunchDarkly;

class CurlEventPublisher implements EventPublisher
{
    private $_sdkKey;
    private $_host;
    private $_port;
    private $_ssl;
    private $_curl = '/usr/bin/env curl';

    function __construct($sdkKey, array $options = array()) {
        $this->_sdkKey = $sdkKey;

        $eventsUri = LDClient::DEFAULT_EVENTS_URI;
        if (isset($options['events_uri'])) {
            $eventsUri = $options['events_uri'];
        }
        $url = parse_url(rtrim($eventsUri,'/'));
        $this->_host = $url['host'];
        $this->_ssl = $url['scheme'] === 'https';
        if (isset($url['port'])) {
            $this->_port = $url['port'];
        } 
        else {
            $this->_port = $this->_ssl ? 443 : 80;
        }
        if (isset($url['path'])) {
            $this->_path = $url['path'];
        }
        else {
            $this->_path = '';
        }

        if (array_key_exists('curl', $options)) {
            $this->_curl = $options['curl'];
        }
    }

    public function publish($payload) {
        $args = $this->createArgs($payload);

        return $this->makeRequest($args);
    }

    private function createArgs($payload) {
        $scheme = $this->_ssl ? "https://" : "http://";
        $args = " -X POST";
        $args.= " -H 'Content-Type: application/json'";
        $args.= " -H " . escapeshellarg("Authorization: " . $this->_sdkKey);
        $args.= " -H 'User-Agent: PHPClient/" . LDClient::VERSION . "'";
        $args.= " -H 'Accept: application/json'";
        $args.= " -d " . escapeshellarg($payload);
        $args.= " " . escapeshellarg($scheme . $this->_host . ":" . $this->_port . $this->_path . "/bulk");
        return $args;
    }

    private function makeRequest($args) {
        $cmd = $this->_curl . " " . $args . ">> /dev/null 2>&1 &";
        shell_exec($cmd);
        return true;
    }
}