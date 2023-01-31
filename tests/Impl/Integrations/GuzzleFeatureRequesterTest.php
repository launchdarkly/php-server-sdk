<?php

namespace LaunchDarkly\Tests\Impl\Integrations;

use GuzzleHttp\Client;
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
    }
}
