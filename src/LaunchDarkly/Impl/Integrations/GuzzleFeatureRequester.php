<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Integrations;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\Impl\UnrecoverableHTTPStatusException;
use LaunchDarkly\Impl\Util;
use LaunchDarkly\Subsystems\FeatureRequester;
use Psr\Log\LoggerInterface;

/**
 * @ignore
 * @internal
 */
class GuzzleFeatureRequester implements FeatureRequester
{
    const SDK_FLAGS = "sdk/flags";
    const SDK_SEGMENTS = "sdk/segments";
    private Client $_client;
    private LoggerInterface $_logger;

    public function __construct(string $baseUri, string $sdkKey, array $options)
    {
        $baseUri = \LaunchDarkly\Impl\Util::adjustBaseUri($baseUri);

        $this->_logger = $options['logger'];
        $stack = HandlerStack::create();
        if (class_exists('\Kevinrob\GuzzleCache\CacheMiddleware')) {
            $stack->push(
                new CacheMiddleware(
                    new PublicCacheStrategy($options['cache'] ?? null)
                ),
                'cache'
            );
        }

        $defaults = [
            'headers' => Util::defaultHeaders($sdkKey, $options['application_info'] ?? null),
            'timeout' => $options['timeout'],
            'connect_timeout' => $options['connect_timeout'],
            'handler' => $stack,
            'debug' => $options['debug'] ?? false,
            'base_uri' => $baseUri
        ];

        $this->_client = new Client($defaults);
    }

    /**
     * Gets feature data from a likely cached store
     *
     * @param string $key feature key
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function getFeature(string $key): ?FeatureFlag
    {
        try {
            $response = $this->_client->get(self::SDK_FLAGS . "/" . $key);
            $body = $response->getBody();
            return FeatureFlag::decode(json_decode($body->getContents(), true));
        } catch (BadResponseException $e) {
            /** @psalm-suppress PossiblyNullReference (resolved in guzzle 7) */
            $code = $e->getResponse()->getStatusCode();
            if ($code == 404) {
                $this->_logger->warning("GuzzleFeatureRequester::get returned 404. Feature flag does not exist for key: " . $key);
            } else {
                $this->handleUnexpectedStatus($code, "GuzzleFeatureRequester::get");
            }
            return null;
        }
    }

    /**
     * Gets segment data from a likely cached store
     *
     * @param string $key segment key
     * @return Segment|null The decoded Segment, or null if missing
     */
    public function getSegment(string $key): ?Segment
    {
        try {
            $response = $this->_client->get(self::SDK_SEGMENTS . "/" . $key);
            $body = $response->getBody();
            return Segment::decode(json_decode($body->getContents(), true));
        } catch (BadResponseException $e) {
            /** @psalm-suppress PossiblyNullReference (resolved in guzzle 7) */
            $code = $e->getResponse()->getStatusCode();
            if ($code == 404) {
                $this->_logger->warning("GuzzleFeatureRequester::get returned 404. Segment does not exist for key: " . $key);
            } else {
                $this->handleUnexpectedStatus($code, "GuzzleFeatureRequester::get");
            }
            return null;
        }
    }

    /**
     * Gets all features from a likely cached store
     *
     * @return array<string, FeatureFlag>|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures(): ?array
    {
        try {
            $response = $this->_client->get(self::SDK_FLAGS);
            $body = $response->getBody();
            return array_map(FeatureFlag::getDecoder(), json_decode($body->getContents(), true));
        } catch (BadResponseException $e) {
            /** @psalm-suppress PossiblyNullReference (resolved in guzzle 7) */
            $this->handleUnexpectedStatus($e->getResponse()->getStatusCode(), "GuzzleFeatureRequester::getAll");
            return null;
        }
    }

    private function handleUnexpectedStatus(int $code, string $method): void
    {
        $this->_logger->error(Util::httpErrorMessage($code, $method, 'default value was returned'));
        if (!Util::isHttpErrorRecoverable($code)) {
            throw new UnrecoverableHTTPStatusException($code);
        }
    }
}
