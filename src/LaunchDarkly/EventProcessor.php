<?php
namespace LaunchDarkly;

/**
 * @internal
 */
class EventProcessor {

  private $_apiKey;
  private $_queue;
  private $_capacity;
  private $_timeout;
  private $_host;
  private $_port;
  private $_ssl;

  public function __construct($apiKey, $options = array()) {
    $this->_apiKey = $apiKey;
    if (!isset($options['events_uri'])) {
        $this->_host = 'events.launchdarkly.com';
        $this->_port = 443;
        $this->_ssl = true;
        $this->_path = '';
    } 
    else {
        $url = parse_url(rtrim($options['events_uri'],'/'));
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
    }

    $this->_capacity = $options['capacity'];
    $this->_timeout = $options['timeout'];

    $this->_queue = array(); 
  }

  public function __destruct() {
    $this->flush();
  }

  public function sendEvent($event) {
    return $this->enqueue($event);
  }

  public function enqueue($event) {
    if (count($this->_queue) > $this->_capacity) {
      return false;
    }

    array_push($this->_queue, $event);

    return true;
  }

  protected function flush() {
    if (empty($this->_queue)) {
      return null;
    }

    $payload = json_encode($this->_queue);

    $args = $this->createArgs($payload);

    return $this->makeRequest($args);
  }

  private function createArgs($payload) {
    $scheme = $this->_ssl ? "https://" : "http://";
    $args = " -X POST";
    $args.= " -H 'Content-Type: application/json'";
    $args.= " -H " . escapeshellarg("Authorization: api_key " . $this->_apiKey);
    $args.= " -H 'User-Agent: PHPClient/" . LDClient::VERSION . "'";
    $args.= " -H 'Accept: application/json'";
    $args.= " -d " . escapeshellarg($payload);
    $args.= " " . escapeshellarg($scheme . $this->_host . ":" . $this->_port . $this->_path . "/bulk");
    return $args;
  }

  private function makeRequest($args) {
    $cmd = "/usr/bin/env curl " . $args . ">> /dev/null 2>&1 &";
    shell_exec($cmd);
    return true;
  }


}