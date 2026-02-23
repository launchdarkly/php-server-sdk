<?php

namespace LaunchDarkly\Tests\Impl\Integrations;

use Beste\Cache\InMemoryCache;
use GuzzleHttp\Client;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use LaunchDarkly\Impl\Integrations\GuzzleFeatureRequester;
use LaunchDarkly\LDClient;
use LaunchDarkly\Types\ApplicationInfo;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GuzzleFeatureRequesterTest extends TestCase
{
    public function setUp(): void
    {
        if (!getenv("LD_INCLUDE_INTEGRATION_TESTS")) {
            $this->markTestSkipped("Skipping integration test");
        }

        $client = new Client();
        $client->request('DELETE', 'http://localhost:8080/__admin/requests');
    }

    public function testSendsCorrectHeaders(): void
    {
        /** @var LoggerInterface **/
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $appInfo = (new ApplicationInfo())->withId('my-id')->withVersion('my-version');

        $config = [
            'logger' => $logger,
            'timeout' => 3,
            'connect_timeout' => 3,
            'application_info' => $appInfo,
            'instance_id' => 'my-instance-id',
        ];

        $requester = new GuzzleFeatureRequester('http://localhost:8080', 'sdk-key', $config);
        $requester->getFeature("flag-key");

        $requests = [];
        $client = new Client();

        // Provide time for the curl to execute
        $start = time();
        while (time() - $start < 5) {
            $response = $client->request('GET', 'http://localhost:8080/__admin/requests');
            $body = json_decode($response->getBody()->getContents(), true);
            $requests = $body['requests'];

            if ($requests) {
                break;
            }
            usleep(100);
        }

        if (!$requests) {
            $this->fail("Unable to connect to endpoint within specified timeout");
        }

        $this->assertCount(1, $requests);

        $request = $requests[0]['request'];

        // Validate that we hit the right endpoint
        $this->assertEquals('/sdk/flags/flag-key', $request['url']);

        // And validate that we provided all the correct headers
        $headers = $request['headers'];
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('application/json', $headers['Accept']);
        $this->assertEquals('sdk-key', $headers['Authorization']);
        $this->assertEquals('PHPClient/' . LDClient::VERSION, $headers['User-Agent']);
        $this->assertEquals('application-id/my-id application-version/my-version', $headers['X-LaunchDarkly-Tags']);
        $this->assertEquals('my-instance-id', $headers['X-LaunchDarkly-Instance-Id']);
    }

    public function wrapperProvider(): array
    {
        return [
            [null, null, null],
            ['my-wrapper', null, 'my-wrapper'],
            ['my-wrapper', '1.0.0', 'my-wrapper/1.0.0'],
            [null, '1.0.0', null],
        ];
    }

    /**
     * @dataProvider wrapperProvider
     */
    public function testSendsCorrectWrapperNameHeaders(?string $wrapper_name, ?string $wrapper_version, ?string $expected_header): void
    {
        /** @var LoggerInterface **/
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $config = [
            'logger' => $logger,
            'timeout' => 3,
            'connect_timeout' => 3,
        ];

        if ($wrapper_name) {
            $config['wrapper_name'] = $wrapper_name;
        }
        if ($wrapper_version) {
            $config['wrapper_version'] = $wrapper_version;
        }

        $requester = new GuzzleFeatureRequester('http://localhost:8080', 'sdk-key', $config);
        $requester->getFeature("flag-key");

        $requests = [];
        $client = new Client();

        // Provide time for the curl to execute
        $start = time();
        while (time() - $start < 5) {
            $response = $client->request('GET', 'http://localhost:8080/__admin/requests');
            $body = json_decode($response->getBody()->getContents(), true);
            $requests = $body['requests'];

            if ($requests) {
                break;
            }
            usleep(100);
        }

        if (!$requests) {
            $this->fail("Unable to connect to endpoint within specified timeout");
        }

        $this->assertCount(1, $requests);

        $request = $requests[0]['request'];

        // Validate that we hit the right endpoint
        $this->assertEquals('/sdk/flags/flag-key', $request['url']);

        // And validate that we provided all the correct headers
        $headers = $request['headers'];
        if ($expected_header) {
            $this->assertEquals($expected_header, $headers['X-LaunchDarkly-Wrapper']);
        } else {
            $this->assertNotContains('X-LaunchDarkly-Wrapper', $headers);
        }
    }

    public function testTimeoutReturnsDefaultValue(): void
    {
        /** @var LoggerInterface **/
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $config = [
            'logger' => $logger,
            'timeout' => 1, // Set a very short timeout
            'connect_timeout' => 1,
        ];

        $client = new Client();
        // Configure the mock server to delay the response by 2 seconds
        $delayRule = [
            'request' => [
                'url' => '/sdk/flags/delayed-flag',
            ],
            'response' => [
                'fixedDelayMilliseconds' => 2000,
                'status' => 200,
                'body' => '{"key": "delayed-flag", "version": 1}'
            ]
        ];

        $client->request('POST', 'http://localhost:8080/__admin/mappings', ['json' => $delayRule]);

        $requester = new GuzzleFeatureRequester('http://localhost:8080', 'sdk-key', $config);
        $result = $requester->getFeature("delayed-flag");

        // The request should timeout and return null (default value) instead of throwing an exception
        $this->assertNull($result);
    }

    // --- Cache integration tests ---

    private function configureCacheableFlag(string $flagKey): void
    {
        $flagJson = json_encode([
            'key' => $flagKey,
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

        $mapping = [
            'request' => [
                'method' => 'GET',
                'url' => '/sdk/flags/' . $flagKey,
            ],
            'response' => [
                'status' => 200,
                'body' => $flagJson,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Cache-Control' => 'public, max-age=300',
                ],
            ],
        ];

        $client = new Client();
        $client->request('POST', 'http://localhost:8080/__admin/mappings', ['json' => $mapping]);
    }

    private function getServerRequestCount(): int
    {
        $client = new Client();
        $response = $client->request('GET', 'http://localhost:8080/__admin/requests');
        $body = json_decode($response->getBody()->getContents(), true);
        return count($body['requests']);
    }

    public function testDefaultCacheServesFromCacheOnSecondRequest(): void
    {
        $this->configureCacheableFlag('default-cache-flag');

        /** @var LoggerInterface **/
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $requester = new GuzzleFeatureRequester('http://localhost:8080', 'sdk-key', [
            'logger' => $logger,
            'timeout' => 3,
            'connect_timeout' => 3,
        ]);

        $flag1 = $requester->getFeature('default-cache-flag');
        $flag2 = $requester->getFeature('default-cache-flag');

        $this->assertNotNull($flag1);
        $this->assertNotNull($flag2);
        $this->assertEquals('default-cache-flag', $flag1->getKey());
        $this->assertEquals('default-cache-flag', $flag2->getKey());

        // Only one request should have reached the server — the second was served from cache
        $this->assertEquals(1, $this->getServerRequestCount());
    }

    public function testPsr6CacheServesFromCacheOnSecondRequest(): void
    {
        $this->configureCacheableFlag('psr6-cache-flag');

        /** @var LoggerInterface **/
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $pool = new InMemoryCache();
        $requester = new GuzzleFeatureRequester('http://localhost:8080', 'sdk-key', [
            'cache' => $pool,
            'logger' => $logger,
            'timeout' => 3,
            'connect_timeout' => 3,
        ]);

        $flag1 = $requester->getFeature('psr6-cache-flag');
        $flag2 = $requester->getFeature('psr6-cache-flag');

        $this->assertNotNull($flag1);
        $this->assertNotNull($flag2);
        $this->assertEquals('psr6-cache-flag', $flag1->getKey());
        $this->assertEquals('psr6-cache-flag', $flag2->getKey());

        // Only one request should have reached the server — the second was served from PSR-6 cache
        $this->assertEquals(1, $this->getServerRequestCount());
    }

    public function testCacheStorageInterfaceServesFromCacheOnSecondRequest(): void
    {
        $this->configureCacheableFlag('storage-cache-flag');

        /** @var LoggerInterface **/
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $storage = new Psr6CacheStorage(new InMemoryCache());
        $requester = new GuzzleFeatureRequester('http://localhost:8080', 'sdk-key', [
            'cache' => $storage,
            'logger' => $logger,
            'timeout' => 3,
            'connect_timeout' => 3,
        ]);

        $flag1 = $requester->getFeature('storage-cache-flag');
        $flag2 = $requester->getFeature('storage-cache-flag');

        $this->assertNotNull($flag1);
        $this->assertNotNull($flag2);
        $this->assertEquals('storage-cache-flag', $flag1->getKey());
        $this->assertEquals('storage-cache-flag', $flag2->getKey());

        // Only one request should have reached the server — the second was served from CacheStorage cache
        $this->assertEquals(1, $this->getServerRequestCount());
    }
}
