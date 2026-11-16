<?php

namespace LaunchDarkly\Tests\Impl\Integrations;

use Beste\Cache\InMemoryCache;
use GuzzleHttp\Client;
use LaunchDarkly\Impl\Integrations\GuzzleFeatureRequester;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * These tests use WireMock. Thus the Guzzle handler decodes the response body.
 *
 * Set LD_WIREMOCK_URL to use a WireMock server at a different address.
 */
class GuzzleFeatureRequesterGzipTest extends TestCase
{
    private ?string $baseUri = null;

    public function setUp(): void
    {
        if (!getenv("LD_INCLUDE_INTEGRATION_TESTS")) {
            $this->markTestSkipped("Skipping integration test");
        }

        $this->baseUri = getenv('LD_WIREMOCK_URL') ?: 'http://localhost:8080';

        $client = new Client();
        $client->request('DELETE', $this->baseUri . '/__admin/mappings');
        $client->request('DELETE', $this->baseUri . '/__admin/requests');

        // Like the test harness, reject a polling request without gzip in Accept-Encoding.
        $this->addMapping([
            'priority' => 10,
            'request' => ['method' => 'GET', 'urlPattern' => '/.*'],
            'response' => ['status' => 400],
        ]);
    }

    public function tearDown(): void
    {
        if ($this->baseUri === null) {
            return;
        }
        // Remove the mappings. Other tests do not expect the 400 response.
        $client = new Client();
        $client->request('DELETE', $this->baseUri . '/__admin/mappings');
    }

    private function addMapping(array $mapping): void
    {
        $client = new Client();
        $client->request('POST', $this->baseUri . '/__admin/mappings', ['json' => $mapping]);
    }

    private static function flagJson(string $key): string
    {
        return (string) json_encode([
            'key' => $key,
            'version' => 1,
            'on' => false,
            'prerequisites' => [],
            'salt' => '',
            'targets' => [],
            'rules' => [],
            'fallthrough' => ['variation' => 0],
            'offVariation' => 0,
            'variations' => [true],
            'deleted' => false,
        ]);
    }

    private static function acceptsGzip(): array
    {
        return ['Accept-Encoding' => ['contains' => 'gzip']];
    }

    private function addGzipFlagMapping(string $key, array $headers = []): void
    {
        $this->addMapping([
            'priority' => 5,
            'request' => [
                'method' => 'GET',
                'url' => '/sdk/flags/' . $key,
                'headers' => self::acceptsGzip(),
            ],
            'response' => [
                'status' => 200,
                'base64Body' => base64_encode((string) gzencode(self::flagJson($key))),
                'headers' => array_merge([
                    'Content-Type' => 'application/json',
                    'Content-Encoding' => 'gzip',
                ], $headers),
            ],
        ]);
    }

    private function addPlainFlagMapping(string $key): void
    {
        $this->addMapping([
            'priority' => 5,
            'request' => [
                'method' => 'GET',
                'url' => '/sdk/flags/' . $key,
                'headers' => self::acceptsGzip(),
            ],
            'response' => [
                'status' => 200,
                'body' => self::flagJson($key),
                'headers' => [
                    'Content-Type' => 'application/json',
                    // WireMock compresses a response when the request accepts gzip.
                    // An explicit Content-Encoding stops this, so the body stays uncompressed.
                    'Content-Encoding' => 'identity',
                ],
            ],
        ]);
    }

    private function makeRequester(array $options = []): GuzzleFeatureRequester
    {
        return new GuzzleFeatureRequester($this->baseUri, 'sdk-key', array_merge([
            'logger' => new NullLogger(),
            'timeout' => 3,
            'connect_timeout' => 3,
        ], $options));
    }

    /**
     * @return array<int, array{url: string, headers: array<string, string>}>
     */
    private function receivedRequests(): array
    {
        $client = new Client();
        $response = $client->request('GET', $this->baseUri . '/__admin/requests');
        $body = json_decode($response->getBody()->getContents(), true);
        // WireMock lists the most recent request first.
        return array_reverse(array_map(fn ($entry) => $entry['request'], $body['requests']));
    }

    public function testRequestAcceptsGzip(): void
    {
        $this->addPlainFlagMapping('plain-flag');

        $this->makeRequester()->getFeature('plain-flag');

        $requests = $this->receivedRequests();
        $this->assertCount(1, $requests);
        $this->assertEquals('/sdk/flags/plain-flag', $requests[0]['url']);
        $this->assertStringContainsString('gzip', $requests[0]['headers']['Accept-Encoding']);
    }

    public function testDecodesGzipResponse(): void
    {
        $this->addGzipFlagMapping('gzip-flag');

        $flag = $this->makeRequester()->getFeature('gzip-flag');

        $this->assertNotNull($flag);
        $this->assertEquals('gzip-flag', $flag->getKey());
    }

    public function testReadsUncompressedResponse(): void
    {
        $this->addPlainFlagMapping('plain-flag');

        $flag = $this->makeRequester()->getFeature('plain-flag');

        $this->assertNotNull($flag);
        $this->assertEquals('plain-flag', $flag->getKey());
    }

    public function testCacheReplaysDecodedGzipResponse(): void
    {
        $this->addGzipFlagMapping('cached-gzip-flag', ['Cache-Control' => 'public, max-age=300']);
        $requester = $this->makeRequester(['cache' => new InMemoryCache()]);

        $flag1 = $requester->getFeature('cached-gzip-flag');
        $flag2 = $requester->getFeature('cached-gzip-flag');

        $this->assertNotNull($flag1);
        $this->assertNotNull($flag2);
        $this->assertEquals('cached-gzip-flag', $flag1->getKey());
        $this->assertEquals('cached-gzip-flag', $flag2->getKey());
        // The cache supplies the second response. Thus the server gets only one request.
        $this->assertCount(1, $this->receivedRequests());
    }

    public function testCacheReturnsDecodedGzipResponseAfterNotModified(): void
    {
        $this->addGzipFlagMapping('revalidated-gzip-flag', [
            'Cache-Control' => 'public, no-cache',
            'ETag' => '"v1"',
        ]);
        $this->addMapping([
            'priority' => 1,
            'request' => [
                'method' => 'GET',
                'url' => '/sdk/flags/revalidated-gzip-flag',
                'headers' => array_merge(self::acceptsGzip(), ['If-None-Match' => ['equalTo' => '"v1"']]),
            ],
            'response' => [
                'status' => 304,
                'headers' => ['ETag' => '"v1"'],
            ],
        ]);
        $requester = $this->makeRequester(['cache' => new InMemoryCache()]);

        $flag1 = $requester->getFeature('revalidated-gzip-flag');
        $flag2 = $requester->getFeature('revalidated-gzip-flag');

        $this->assertNotNull($flag1);
        $this->assertNotNull($flag2);
        $this->assertEquals('revalidated-gzip-flag', $flag1->getKey());
        $this->assertEquals('revalidated-gzip-flag', $flag2->getKey());

        // The second request revalidates the cache entry and the server sends 304.
        $requests = $this->receivedRequests();
        $this->assertCount(2, $requests);
        $this->assertArrayNotHasKey('If-None-Match', $requests[0]['headers']);
        $this->assertEquals('"v1"', $requests[1]['headers']['If-None-Match']);
    }
}
