<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Integrations;

use LaunchDarkly\Impl\Util;
use LaunchDarkly\LDClient;
use LaunchDarkly\Subsystems\EventPublisher;

/**
 * Curl-based implementation of sending events. This is used by default.
 *
 * @ignore
 * @internal
 */
class CurlEventPublisher implements EventPublisher
{
    private string $_host;
    private int $_port;
    private string $_path;
    private bool $_ssl;
    private string $_curl = '/usr/bin/env curl';
    private int $_connectTimeout;
    private bool $_isWindows;

    /** @var array<string, string> */
    private array $_eventHeaders;

    public function __construct(string $sdkKey, array $options = [])
    {
        $baseUri = $options['events_uri'] ?? null;
        if (!$baseUri) {
            $baseUri = LDClient::DEFAULT_EVENTS_URI;
        }
        $eventsUri = \LaunchDarkly\Impl\Util::adjustBaseUri($baseUri);

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

        $this->_eventHeaders = Util::eventHeaders($sdkKey, $options['application_info'] ?? null);
        $this->_connectTimeout = $options['connect_timeout'];
        $this->_isWindows = PHP_OS_FAMILY == 'Windows';
    }

    public function publish(string $payload): bool
    {
        if (!$this->_isWindows) {
            $args = $this->createCurlArgs($payload);
            return $this->makeCurlRequest($args);
        }

        $tmpfile = tempnam(sys_get_temp_dir(), 'ld-');
        if ($tmpfile === false) {
            return false;
        }

        if (file_put_contents($tmpfile, $payload) === false) {
            return false;
        };

        $args = $this->createPowershellArgs($tmpfile);
        $this->makePowershellRequest($args);

        return true;
    }

    private function createCurlArgs(string $payload): string
    {
        $scheme = $this->_ssl ? "https://" : "http://";
        $args = " -X POST";
        $args.= " --connect-timeout " . $this->_connectTimeout;

        foreach ($this->_eventHeaders as $key => $value) {
            if ($key == 'Authorization') {
                $args.= " -H " . escapeshellarg("Authorization: " . $value);
            } else {
                $args.= " -H '$key: $value'";
            }
        }

        $args.= " -d " . escapeshellarg($payload);
        $args.= " " . escapeshellarg($scheme . $this->_host . ":" . $this->_port . $this->_path . "/bulk");
        return $args;
    }

    /**
     * @psalm-suppress ForbiddenCode
     */
    private function makeCurlRequest(string $args): bool
    {
        $cmd = $this->_curl . " " . $args . ">> /dev/null 2>&1 &";
        shell_exec($cmd);
        return true;
    }

    private function createPowershellArgs(string $payloadFile): string
    {
        $headerString = "";
        foreach ($this->_eventHeaders as $key => $value) {
            $headerString .= sprintf("'%s'='%s';", $key, $value);
        }

        $scheme = $this->_ssl ? "https://" : "http://";
        $args = " Invoke-WebRequest";
        $args.= " -Method POST";
        $args.= " -UseBasicParsing";
        $args.= " -InFile $payloadFile";
        $args.= " -H @{" . $headerString . "}";
        $args.= " -Uri " . escapeshellarg($scheme . $this->_host . ":" . $this->_port . $this->_path . "/bulk");
        $args.= " ; Remove-Item $payloadFile";

        return $args;
    }

    /**
     * @psalm-suppress ForbiddenCode
     */
    private function makePowershellRequest(string $args): bool
    {
        $cmd = base64_encode(iconv("UTF-8", "UTF-16LE", utf8_encode($args)));
        shell_exec("start /B powershell.exe -encodedCommand $cmd > nul 2>&1");

        return true;
    }
}
