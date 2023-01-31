<?php

namespace LaunchDarkly\Tests\Impl\Integrations;

use GuzzleHttp\Client;
use LaunchDarkly\Impl\Integrations;
use LaunchDarkly\LDClient;
use LaunchDarkly\Subsystems\EventPublisher;
use LaunchDarkly\Types\ApplicationInfo;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EventPublisherTest extends TestCase
{
    public function setUp(): void
    {
        if (!getenv("LD_INCLUDE_INTEGRATION_TESTS")) {
            $this->markTestSkipped("Skipping integration test");
        }

        $client = new Client();
        $client->request('DELETE', 'http://localhost:8080/__admin/requests');
    }

    public function getEventPublisher(): array
    {
        /** @var LoggerInterface **/
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $appInfo = (new ApplicationInfo())->withId('my-id')->withVersion('my-version');

        $config = [
            'events_uri' => 'http://localhost:8080',
            'timeout' => 3,
            'connect_timeout' => 3,
            'application_info' => $appInfo,
            'logger' => $logger,
        ];

        $curlPublisher = new Integrations\CurlEventPublisher('sdk-key', $config);
        $guzzlePublisher = new Integrations\GuzzleEventPublisher('sdk-key', $config);

        return [
            [$curlPublisher],
            [$guzzlePublisher],
        ];
    }

    /**
     * @dataProvider getEventPublisher
     */
    public function testSendsCorrectBodyAndHeaders($publisher)
    {
        $event = json_encode(["key" => "user-key"]);
        $publisher->publish($event);

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

        // Validate that we hit the right endpoint with the right data
        $this->assertEquals($event, $request['body']);
        $this->assertEquals('/bulk', $request['url']);

        // And validate that we provided all the correct headers
        $headers = $request['headers'];
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('application/json', $headers['Accept']);
        $this->assertEquals('sdk-key', $headers['Authorization']);
        $this->assertEquals('PHPClient/' . LDClient::VERSION, $headers['User-Agent']);
        $this->assertEquals(EventPublisher::CURRENT_SCHEMA_VERSION, $headers['X-LaunchDarkly-Event-Schema']);
        $this->assertEquals('application-id/my-id application-version/my-version', $headers['X-LaunchDarkly-Tags']);
    }
}
