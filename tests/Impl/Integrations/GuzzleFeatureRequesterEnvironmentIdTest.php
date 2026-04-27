<?php

declare(strict_types=1);

namespace LaunchDarkly\Tests\Impl\Integrations;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use LaunchDarkly\Impl\EnvironmentIdProvider;
use LaunchDarkly\Impl\Integrations\GuzzleFeatureRequester;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Unit-level coverage of the X-Ld-Envid response-header capture in
 * GuzzleFeatureRequester. The standard GuzzleFeatureRequesterTest is
 * gated on WireMock; this class uses Guzzle's MockHandler so it runs
 * everywhere.
 */
class GuzzleFeatureRequesterEnvironmentIdTest extends TestCase
{
    private function makeRequester(MockHandler $mock, ?EnvironmentIdProvider $provider): GuzzleFeatureRequester
    {
        $options = [
            'logger' => new NullLogger(),
            'timeout' => 3,
            'connect_timeout' => 3,
            '_environment_id_provider' => $provider,
            // Inject our handler into Guzzle by way of GuzzleFeatureRequester's existing
            // base_uri/timeout/etc construction: we replace the client's HandlerStack
            // by passing our own via a private property override below.
        ];

        $requester = new GuzzleFeatureRequester('http://example.invalid', 'sdk-key', $options);
        // Swap the internal Guzzle client for one wired to MockHandler. The captureEnvironmentId
        // logic does not depend on the rest of the client setup, so this is sufficient.
        $reflection = new \ReflectionProperty($requester, '_client');
        $reflection->setValue($requester, new \GuzzleHttp\Client(['handler' => HandlerStack::create($mock)]));
        return $requester;
    }

    public function testHeaderPopulatesProvider(): void
    {
        $provider = new EnvironmentIdProvider();
        $mock = new MockHandler([
            new Response(200, ['X-Ld-Envid' => 'env-abc'], '{"key": "flag", "version": 1, "on": false, "variations": ["v"], "offVariation": 0, "fallthrough": {"variation": 0}}'),
        ]);

        $this->makeRequester($mock, $provider)->getFeature('flag');
        $this->assertSame('env-abc', $provider->get());
    }

    public function testMissingHeaderLeavesProviderNull(): void
    {
        $provider = new EnvironmentIdProvider();
        $mock = new MockHandler([
            new Response(200, [], '{"key": "flag", "version": 1, "on": false, "variations": ["v"], "offVariation": 0, "fallthrough": {"variation": 0}}'),
        ]);

        $this->makeRequester($mock, $provider)->getFeature('flag');
        $this->assertNull($provider->get());
    }

    public function testHeaderCaseInsensitive(): void
    {
        $provider = new EnvironmentIdProvider();
        $mock = new MockHandler([
            new Response(200, ['x-ld-envid' => 'env-lower'], '{"key": "flag", "version": 1, "on": false, "variations": ["v"], "offVariation": 0, "fallthrough": {"variation": 0}}'),
        ]);

        $this->makeRequester($mock, $provider)->getFeature('flag');
        $this->assertSame('env-lower', $provider->get());
    }

    public function testGetAllFeaturesAlsoCapturesHeader(): void
    {
        $provider = new EnvironmentIdProvider();
        $mock = new MockHandler([
            new Response(200, ['X-Ld-Envid' => 'env-all'], '{}'),
        ]);

        $this->makeRequester($mock, $provider)->getAllFeatures();
        $this->assertSame('env-all', $provider->get());
    }

    public function test404ResponsePopulatesProvider(): void
    {
        // LaunchDarkly's polling endpoints emit X-Ld-Envid on error responses too,
        // so a 404 for an unknown flag should still surface the env ID.
        $provider = new EnvironmentIdProvider();
        $mock = new MockHandler([
            new Response(404, ['X-Ld-Envid' => 'env-404'], '{}'),
        ]);

        $this->makeRequester($mock, $provider)->getFeature('flag');
        $this->assertSame('env-404', $provider->get());
    }

    public function test5xxResponsePopulatesProvider(): void
    {
        // Non-recoverable HTTP errors throw UnrecoverableHTTPStatusException after
        // capturing the env ID so it remains observable to subsequent hook contexts.
        $provider = new EnvironmentIdProvider();
        $mock = new MockHandler([
            new Response(500, ['X-Ld-Envid' => 'env-500'], '{}'),
        ]);

        try {
            $this->makeRequester($mock, $provider)->getFeature('flag');
        } catch (\LaunchDarkly\Impl\UnrecoverableHTTPStatusException) {
            // expected for non-recoverable status
        }
        $this->assertSame('env-500', $provider->get());
    }

    public function testNoProviderInOptionsWorksFine(): void
    {
        // When LDClient does not inject a provider (e.g. external tooling instantiates
        // the requester directly), the requester must not error.
        $mock = new MockHandler([
            new Response(200, ['X-Ld-Envid' => 'env-anything'], '{"key": "flag", "version": 1, "on": false, "variations": ["v"], "offVariation": 0, "fallthrough": {"variation": 0}}'),
        ]);

        $this->makeRequester($mock, null)->getFeature('flag');
        $this->assertTrue(true); // no exception raised
    }
}
