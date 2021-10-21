<?php

namespace LaunchDarkly\Tests\Impl\Integrations;

use GuzzleHttp\Client;
use LaunchDarkly\EventPublisher;
use LaunchDarkly\Impl\Integrations;
use LaunchDarkly\LDClient;
use PHPUnit\Framework\TestCase;

class CurlEventPublisherTest extends TestCase
{
    public function setUp(): void
    {
        if (!getenv("LD_INCLUDE_INTEGRATION_TESTS")) {
            $this->markTestSkipped("Skipping integration test");
        }

        $client = new Client();
        $client->request('DELETE', 'http://localhost:8080/__admin/requests');
    }

    public function testSendsCorrectBodyAndHeaders()
    {
        $event = json_encode(["key" => "user-key"]);
        $publisher = new Integrations\CurlEventPublisher('sdk-key', ['events_uri' => 'http://localhost:8080']);
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
    }
}
