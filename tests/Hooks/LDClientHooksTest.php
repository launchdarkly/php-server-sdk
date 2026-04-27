<?php

declare(strict_types=1);

namespace LaunchDarkly\Tests\Hooks;

use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Hooks\EvaluationSeriesContext;
use LaunchDarkly\Hooks\Hook;
use LaunchDarkly\Hooks\Metadata;
use LaunchDarkly\Hooks\TrackSeriesContext;
use LaunchDarkly\LDClient;
use LaunchDarkly\LDContext;
use LaunchDarkly\Migrations\Stage;
use LaunchDarkly\Tests\MockEventProcessor;
use LaunchDarkly\Tests\MockFeatureRequester;
use LaunchDarkly\Tests\ModelBuilders;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LDClientHooksTest extends TestCase
{
    private MockFeatureRequester $mockRequester;

    public function setUp(): void
    {
        $this->mockRequester = new MockFeatureRequester();
    }

    /**
     * @param array<string, mixed> $overrideOptions
     */
    private function makeClient(array $overrideOptions = []): LDClient
    {
        $options = [
            'feature_requester' => $this->mockRequester,
            'event_processor' => new MockEventProcessor(),
            'logger' => new \Psr\Log\NullLogger(),
        ];
        return new LDClient('someKey', array_merge($options, $overrideOptions));
    }

    private function addFlag(string $key, mixed $value): void
    {
        $flag = ModelBuilders::flagBuilder($key)
            ->version(1)
            ->on(false)
            ->variations($value)
            ->offVariation(0)
            ->fallthroughVariation(0)
            ->build();
        $this->mockRequester->addFlag($flag);
    }

    public function testVariationInvokesBeforeAndAfterInOrder(): void
    {
        $this->addFlag('flag', 'v');
        $shared = new CallLog();
        $a = new RecordingHook('A', $shared);
        $b = new RecordingHook('B', $shared);

        $client = $this->makeClient(['hooks' => [$a, $b]]);
        $client->variation('flag', LDContext::create('u'), 'default');

        $this->assertSame(
            ['A:beforeEvaluation', 'B:beforeEvaluation', 'B:afterEvaluation', 'A:afterEvaluation'],
            array_map(fn ($e) => $e['hook'] . ':' . $e['stage'], $shared->calls),
        );
    }

    public function testVariationPassesCorrectMethodAndContext(): void
    {
        $this->addFlag('flag', 'v');
        $hook = new RecordingHook('A');
        $client = $this->makeClient(['hooks' => [$hook]]);

        $context = LDContext::create('u');
        $client->variation('flag', $context, 'the-default');

        /** @var EvaluationSeriesContext $seriesCtx */
        $seriesCtx = $hook->calls[0]['ctx'];
        $this->assertSame('flag', $seriesCtx->flagKey);
        $this->assertSame('the-default', $seriesCtx->defaultValue);
        $this->assertSame('variation', $seriesCtx->method);
        $this->assertSame($context, $seriesCtx->context);
    }

    public function testVariationDetailUsesVariationDetailMethodName(): void
    {
        $this->addFlag('flag', 'v');
        $hook = new RecordingHook('A');
        $client = $this->makeClient(['hooks' => [$hook]]);

        $client->variationDetail('flag', LDContext::create('u'), 'default');

        /** @var EvaluationSeriesContext $seriesCtx */
        $seriesCtx = $hook->calls[0]['ctx'];
        $this->assertSame('variationDetail', $seriesCtx->method);
    }

    public function testMigrationVariationUsesMigrationVariationMethodName(): void
    {
        $flag = ModelBuilders::flagBuilder('migration')
            ->version(1)
            ->on(false)
            ->variations('off')
            ->offVariation(0)
            ->fallthroughVariation(0)
            ->build();
        $this->mockRequester->addFlag($flag);

        $hook = new RecordingHook('A');
        $client = $this->makeClient(['hooks' => [$hook]]);

        $client->migrationVariation('migration', LDContext::create('u'), Stage::OFF);

        /** @var EvaluationSeriesContext $seriesCtx */
        $seriesCtx = $hook->calls[0]['ctx'];
        $this->assertSame('migrationVariation', $seriesCtx->method);
    }

    public function testAfterEvaluationReceivesEvaluationDetail(): void
    {
        $this->addFlag('flag', 'v');
        $hook = new RecordingHook('A');
        $client = $this->makeClient(['hooks' => [$hook]]);

        $client->variation('flag', LDContext::create('u'), 'default');

        $afterCall = $hook->calls[1];
        $this->assertSame('afterEvaluation', $afterCall['stage']);
        $this->assertSame('v', $afterCall['detail']->getValue());
    }

    public function testHookExceptionDoesNotBreakEvaluation(): void
    {
        $this->addFlag('flag', 'v');
        $hook = new RecordingHook('A', throwFrom: [
            'beforeEvaluation' => new RuntimeException('x'),
            'afterEvaluation' => new RuntimeException('y'),
        ]);
        $client = $this->makeClient(['hooks' => [$hook]]);

        $value = $client->variation('flag', LDContext::create('u'), 'default');
        $this->assertSame('v', $value);
    }

    public function testAfterEvaluationFiresWhenEvaluationThrowsNonExceptionThrowable(): void
    {
        // evaluateInternal catches \Throwable so a non-Exception error (e.g. TypeError)
        // from a downstream component is converted into an EXCEPTION_ERROR detail rather
        // than propagating. Hooks must still fire, and afterEvaluation must see that detail.
        $throwingRequester = new class extends MockFeatureRequester {
            public function getFeature(string $key): ?\LaunchDarkly\Impl\Model\FeatureFlag
            {
                throw new \TypeError('synthetic');
            }
        };
        $hook = new RecordingHook('A');
        $client = $this->makeClient(['feature_requester' => $throwingRequester, 'hooks' => [$hook]]);

        $value = $client->variation('flag', LDContext::create('u'), 'default');
        $this->assertSame('default', $value);

        $this->assertCount(2, $hook->calls);
        $this->assertSame('beforeEvaluation', $hook->calls[0]['stage']);
        $this->assertSame('afterEvaluation', $hook->calls[1]['stage']);
        $this->assertSame(
            EvaluationReason::EXCEPTION_ERROR,
            $hook->calls[1]['detail']->getReason()->getErrorKind(),
        );
    }

    public function testHookFiresWhenContextIsInvalid(): void
    {
        // Invalid context — variation returns default, but hooks should still fire so
        // telemetry can be emitted for failed evaluations.
        $hook = new RecordingHook('A');
        $client = $this->makeClient(['hooks' => [$hook]]);

        $client->variation('flag', LDContext::create(''), 'default');

        $this->assertCount(2, $hook->calls);
        $this->assertSame('beforeEvaluation', $hook->calls[0]['stage']);
        $this->assertSame('afterEvaluation', $hook->calls[1]['stage']);
        $this->assertSame(
            EvaluationReason::USER_NOT_SPECIFIED_ERROR,
            $hook->calls[1]['detail']->getReason()->getErrorKind(),
        );
    }

    public function testTrackInvokesAfterTrack(): void
    {
        $hook = new RecordingHook('A');
        $client = $this->makeClient(['hooks' => [$hook]]);

        $client->track('event', LDContext::create('u'), ['d' => 1], 2.5);

        $this->assertCount(1, $hook->calls);
        /** @var TrackSeriesContext $tctx */
        $tctx = $hook->calls[0]['ctx'];
        $this->assertSame('event', $tctx->key);
        $this->assertSame(['d' => 1], $tctx->data);
        $this->assertSame(2.5, $tctx->metricValue);
    }

    public function testTrackDoesNotInvokeAfterTrackOnInvalidContext(): void
    {
        $hook = new RecordingHook('A');
        $client = $this->makeClient(['hooks' => [$hook]]);

        $client->track('event', LDContext::create(''), null);

        $this->assertCount(0, $hook->calls);
    }

    public function testTrackMigrationOperationDoesNotInvokeAfterTrack(): void
    {
        $flag = ModelBuilders::flagBuilder('migration')
            ->version(1)
            ->on(false)
            ->variations('off')
            ->offVariation(0)
            ->fallthroughVariation(0)
            ->build();
        $this->mockRequester->addFlag($flag);

        $hook = new RecordingHook('A');
        $client = $this->makeClient(['hooks' => [$hook]]);

        $result = $client->migrationVariation('migration', LDContext::create('u'), Stage::OFF);
        $tracker = $result['tracker'];
        // Populate the tracker enough that build() returns an event (not a string error).
        $tracker->operation(\LaunchDarkly\Migrations\Operation::READ);
        $tracker->invoked(\LaunchDarkly\Migrations\Origin::OLD);

        // Clear hook calls from migrationVariation above.
        $hook->calls = [];

        $client->trackMigrationOperation($tracker);

        // afterTrack must not fire for migration events (spec 1.6 — only custom events).
        $this->assertCount(0, $hook->calls);
    }

    public function testAddHookAfterConstruction(): void
    {
        $this->addFlag('flag', 'v');
        $client = $this->makeClient();

        $hook = new RecordingHook('A');
        $client->addHook($hook);

        $client->variation('flag', LDContext::create('u'), 'default');
        $this->assertCount(2, $hook->calls);
    }

    public function testNonHookEntriesInOptionAreIgnored(): void
    {
        $this->addFlag('flag', 'v');
        $hook = new RecordingHook('A');
        $client = $this->makeClient(['hooks' => [$hook, 'not-a-hook', new \stdClass()]]);

        // Should not throw; invalid entries silently dropped (with a warning log).
        $client->variation('flag', LDContext::create('u'), 'default');
        $this->assertCount(2, $hook->calls);
    }

    public function testHookRegisteredViaAllMechanismsAllFire(): void
    {
        $this->addFlag('flag', 'v');
        $shared = new CallLog();
        $a = new RecordingHook('A', $shared);
        $b = new RecordingHook('B', $shared);
        $client = $this->makeClient(['hooks' => [$a]]);
        $client->addHook($b);

        $client->variation('flag', LDContext::create('u'), 'default');
        $this->assertSame(
            ['A:beforeEvaluation', 'B:beforeEvaluation', 'B:afterEvaluation', 'A:afterEvaluation'],
            array_map(fn ($e) => $e['hook'] . ':' . $e['stage'], $shared->calls),
        );
    }

    public function testEmptyHooksDoesNotBreakEvaluation(): void
    {
        $this->addFlag('flag', 'v');
        $client = $this->makeClient();

        $value = $client->variation('flag', LDContext::create('u'), 'default');
        $this->assertSame('v', $value);
    }

    public function testNoOpHookImplementationDoesNotInterfere(): void
    {
        $noOp = new class extends Hook {
            public function getMetadata(): Metadata
            {
                return new Metadata('NoOp');
            }
        };

        $this->addFlag('flag', 'v');
        $client = $this->makeClient(['hooks' => [$noOp]]);

        $value = $client->variation('flag', LDContext::create('u'), 'default');
        $this->assertSame('v', $value);
    }

    public function testEnvironmentIdAvailableInAfterEvaluationOnFirstCall(): void
    {
        // Simulate what GuzzleFeatureRequester does: write to the env ID holder during the
        // fetch that happens inside variation(). beforeEvaluation runs prior to the fetch
        // and sees null; afterEvaluation runs after and sees the captured ID.
        $factory = function (string $baseUri, string $sdkKey, array $options) {
            return new EnvIdSettingFeatureRequester($options, 'env-from-fetch');
        };
        $hook = new RecordingHook('A');
        $client = $this->makeClient(['feature_requester' => $factory, 'hooks' => [$hook]]);

        $client->variation('any-flag', LDContext::create('u'), 'default');

        $this->assertCount(2, $hook->calls);
        $this->assertNull($hook->calls[0]['ctx']->environmentId);
        $this->assertSame('env-from-fetch', $hook->calls[1]['ctx']->environmentId);
    }

    public function testEnvironmentIdAvailableInBothStagesOnSubsequentCalls(): void
    {
        // After the first variation populates the holder, subsequent calls within the same
        // LDClient lifetime see the env ID in beforeEvaluation as well.
        $factory = function (string $baseUri, string $sdkKey, array $options) {
            return new EnvIdSettingFeatureRequester($options, 'env-from-fetch');
        };
        $hook = new RecordingHook('A');
        $client = $this->makeClient(['feature_requester' => $factory, 'hooks' => [$hook]]);

        $client->variation('flag-1', LDContext::create('u'), 'default');
        $hook->calls = [];
        $client->variation('flag-2', LDContext::create('u'), 'default');

        $this->assertCount(2, $hook->calls);
        $this->assertSame('env-from-fetch', $hook->calls[0]['ctx']->environmentId);
        $this->assertSame('env-from-fetch', $hook->calls[1]['ctx']->environmentId);
    }

    public function testEnvironmentIdNullWhenRequesterDoesNotPopulate(): void
    {
        // Persistent-store feature requesters do not write to the holder. Hooks see null
        // for environmentId in both stages, regardless of how many calls occur.
        $hook = new RecordingHook('A');
        $this->addFlag('flag', 'v');
        $client = $this->makeClient(['hooks' => [$hook]]);

        $client->variation('flag', LDContext::create('u'), 'default');
        $client->variation('flag', LDContext::create('u'), 'default');

        foreach ($hook->calls as $call) {
            $this->assertNull($call['ctx']->environmentId);
        }
    }
}
