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
    private int $_timeout;
    private bool $_isWindows;

    /**
     * The directory named by the payload_temp_dir option, or null if the option is not set.
     */
    private ?string $_payloadTempDir;

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
            $this->_curl = escapeshellcmd($options['curl']);
        }

        $this->_eventHeaders = Util::eventHeaders($sdkKey, $options);
        $this->_connectTimeout = intval($options['connect_timeout']);
        $this->_timeout = intval($options['timeout']);
        $this->_isWindows = PHP_OS_FAMILY == 'Windows';
        $this->_payloadTempDir = $this->resolvePayloadTempDir($options['payload_temp_dir'] ?? null);
    }

    /**
     * Reads the payload_temp_dir option.
     *
     * The value true means the system temporary directory. A non-empty string is a directory path.
     * Any other value means the option is not set.
     */
    private function resolvePayloadTempDir(mixed $option): ?string
    {
        if ($option === true) {
            return sys_get_temp_dir();
        }

        return (is_string($option) && $option !== '') ? $option : null;
    }

    public function publish(string $payload): bool
    {
        // Windows always sends the payload from a file.
        if ($this->_isWindows) {
            $payloadFile = $this->writePayloadFile($payload);
            if ($payloadFile === null) {
                return false;
            }

            return $this->makePowershellRequest($payloadFile);
        }

        if ($this->_payloadTempDir === null) {
            return $this->makeCurlRequest($payload);
        }

        $payloadFile = $this->writePayloadFile($payload);
        if ($payloadFile === null) {
            return false;
        }

        return $this->makeCurlRequest($payload, $payloadFile);
    }

    /**
     * Writes the payload to a new file.
     *
     * The file goes in the directory named by the payload_temp_dir option. The default is the
     * system temporary directory.
     *
     * @return ?string the path of the file, or null if the whole payload could not be written
     */
    private function writePayloadFile(string $payload): ?string
    {
        $payloadFile = tempnam($this->_payloadTempDir ?? sys_get_temp_dir(), 'ld-');
        if ($payloadFile === false) {
            return null;
        }

        if (file_put_contents($payloadFile, $payload) !== strlen($payload)) {
            unlink($payloadFile);
            return null;
        }

        return $payloadFile;
    }

    /**
     * Sends the payload with curl, in the background.
     *
     * Curl reads the payload from the command line. Name a file that already holds the payload to
     * have curl read the file instead. The file is removed when the request ends.
     *
     * @psalm-suppress ForbiddenCode
     */
    private function makeCurlRequest(string $payload, ?string $payloadFile = null): bool
    {
        $scheme = $this->_ssl ? "https://" : "http://";
        $args = " -X POST";
        $args.= " --connect-timeout " . $this->_connectTimeout;
        $args.= " --max-time " . $this->_timeout;

        foreach ($this->_eventHeaders as $key => $value) {
            $args.= " -H " . escapeshellarg("$key: $value");
        }

        if ($payloadFile === null) {
            $args.= " -d " . escapeshellarg($payload);
        } else {
            $args.= " --data-binary @" . escapeshellarg($payloadFile);
        }

        $args.= " " . escapeshellarg($scheme . $this->_host . ":" . $this->_port . $this->_path . "/bulk");

        $cmd = $this->_curl . $args;
        if ($payloadFile !== null) {
            // The subshell keeps the removal grouped with the request that reads the file.
            $cmd = "( " . $cmd . " ; rm -f " . escapeshellarg($payloadFile) . " )";
        }

        shell_exec($cmd . " >> /dev/null 2>&1 &");
        return true;
    }

    /**
     * Sends the payload with PowerShell, in the background.
     *
     * PowerShell reads the payload from the named file. The file is removed when the request ends.
     *
     * @psalm-suppress ForbiddenCode
     */
    private function makePowershellRequest(string $payloadFile): bool
    {
        $headerString = "";
        foreach ($this->_eventHeaders as $key => $value) {
            $escapedKey = str_replace("'", "''", $key);
            $escapedValue = str_replace("'", "''", strval($value));
            $headerString .= sprintf("'%s'='%s';", $escapedKey, $escapedValue);
        }

        $scheme = $this->_ssl ? "https://" : "http://";
        $args = " Invoke-WebRequest";
        $args.= " -Method POST";
        $args.= " -UseBasicParsing";
        $args.= " -InFile '$payloadFile'";
        $args.= " -H @{" . $headerString . "}";
        $args.= " -Uri " . escapeshellarg($scheme . $this->_host . ":" . $this->_port . $this->_path . "/bulk");
        $args.= " ; Remove-Item '$payloadFile'";

        $cmd = base64_encode(iconv('ISO-8859-1', 'UTF-16LE', $args));
        shell_exec("start /B powershell.exe -encodedCommand $cmd > nul 2>&1");

        return true;
    }
}
