<?php

namespace LaunchDarkly\Tests\Migrations;

use Exception;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\LDClient;
use LaunchDarkly\LDContext;
use LaunchDarkly\Migrations\Migrator;
use LaunchDarkly\Migrations\MigratorBuilder;
use LaunchDarkly\Migrations\Origin;
use LaunchDarkly\Migrations\Stage;
use LaunchDarkly\Tests\MockEventProcessor;
use LaunchDarkly\Tests\MockFeatureRequester;
use LaunchDarkly\Tests\ModelBuilders;
use LaunchDarkly\Types\Result;

class MigratorTest extends \PHPUnit\Framework\TestCase
{
    private MigratorBuilder $builder;
    private MockEventProcessor $eventProcessor;

    public function setUp(): void
    {
        $requester = new MockFeatureRequester();

        foreach (Stage::cases() as $stage) {
            $requester->addFlag($this->makeOffFlagWithValue($stage->value, $stage->value));
        }

        $this->eventProcessor = new MockEventProcessor();
        $options = [
            'feature_requester' => $requester,
            'event_processor' => $this->eventProcessor,
        ];

        $client = new LDClient("someKey", $options);
        $successFn = fn () => Result::success(null);

        $this->builder = new MigratorBuilder($client);
        $this->builder->trackLatency(false)
            ->trackErrors(false)
            ->read($successFn, $successFn)
            ->write($successFn, $successFn);
    }

    private function makeOffFlagWithValue(string $key, string $value): FeatureFlag
    {
        return ModelBuilders::flagBuilder($key)
            ->version(100)
            ->on(false)
            ->variations('FALLTHROUGH', $value)
            ->fallthroughVariation(0)
            ->offVariation(1)
            ->build();
    }

    public function payloadReadPassthroughProvider(): array
    {
        return [
            [Stage::OFF, 1],
            [Stage::DUALWRITE, 1],
            [Stage::SHADOW, 2],
            [Stage::LIVE, 2],
            [Stage::RAMPDOWN, 1],
            [Stage::COMPLETE, 1],
        ];
    }

    /**
     * @dataProvider payloadReadPassthroughProvider
     */
    public function testPayloadPassesThroughRead(Stage $stage, int $expectedCount): void
    {
        $payloads = [];
        $capturePayloads = function (mixed $payload) use (&$payloads): Result {
            $payloads[] = $payload;
            return Result::success(null);
        };

        $this->builder->read($capturePayloads, $capturePayloads);
        /** @var Migrator */
        $migrator = $this->builder->build()->value;

        $result = $migrator->read($stage->value, LDContext::create('user-key'), Stage::LIVE, "payload");

        $this->assertTrue($result->isSuccessful());
        $this->assertCount($expectedCount, $payloads);
        $this->assertEquals('payload', array_unique($payloads)[0]);
    }

    public function payloadWritePassthroughProvider(): array
    {
        return [
            [Stage::OFF, 1],
            [Stage::DUALWRITE, 2],
            [Stage::SHADOW, 2],
            [Stage::LIVE, 2],
            [Stage::RAMPDOWN, 2],
            [Stage::COMPLETE, 1],
        ];
    }

    /**
     * @dataProvider payloadWritePassthroughProvider
     */
    public function testPayloadPassesThroughWrite(Stage $stage, int $expectedCount): void
    {
        $payloads = [];
        $capturePayloads = function (mixed $payload) use (&$payloads): Result {
            $payloads[] = $payload;
            return Result::success(null);
        };

        $this->builder->write($capturePayloads, $capturePayloads);
        /** @var Migrator */
        $migrator = $this->builder->build()->value;

        $writeResult = $migrator->write($stage->value, LDContext::create('user-key'), Stage::LIVE, "payload");

        $this->assertTrue($writeResult->authoritative->isSuccessful());
        $this->assertCount($expectedCount, $payloads);
        $this->assertEquals('payload', array_unique($payloads)[0]);
    }

    public function readStageOriginProvider(): array
    {
        return [
            [Stage::OFF, [Origin::OLD]],
            [Stage::DUALWRITE, [Origin::OLD]],
            [Stage::SHADOW, [Origin::OLD, Origin::NEW]],
            [Stage::LIVE, [Origin::OLD, Origin::NEW]],
            [Stage::RAMPDOWN, [Origin::NEW]],
            [Stage::COMPLETE, [Origin::NEW]],
        ];
    }

    /**
     * @dataProvider readStageOriginProvider
     */
    public function testTrackingInvokedForReads(Stage $stage, array $origins): void
    {
        /** @var Migrator */
        $migrator = $this->builder->build()->value;
        $migrator->read($stage->value, LDContext::create('user-key'), Stage::LIVE);

        $events = $this->eventProcessor->getEvents();

        $this->assertCount(2, $events);

        $event = $events[1]; // First event is evaluation result
        $invoked = $event['measurements'][0];

        $this->assertEquals('invoked', $invoked['key']);

        array_map(fn ($origin) => $this->assertTrue($invoked['values'][$origin->value]), $origins);
    }

    /**
     * @dataProvider readStageOriginProvider
     */
    public function testTrackingLatencyForReads(Stage $stage, array $origins): void
    {
        $delayed = function (mixed $payload): Result {
            return Result::success(null);
        };

        $this->builder->read($delayed, $delayed);
        $this->builder->trackLatency(true);

        /** @var Migrator */
        $migrator = $this->builder->build()->value;
        $migrator->read($stage->value, LDContext::create('user-key'), Stage::LIVE);

        $events = $this->eventProcessor->getEvents();

        $this->assertCount(2, $events);

        $event = $events[1]; // First event is evaluation result
        $latencies = $event['measurements'][1]; // First measurement is invoked

        $this->assertEquals('latency_ms', $latencies['key']);
        foreach ($origins as $origin) {
            $this->assertGreaterThanOrEqual($latencies['values'][$origin->value], 100);
        }
    }

    /**
     * @dataProvider readStageOriginProvider
     */
    public function testTrackingErrorsForReads(Stage $stage, array $origins): void
    {
        $this->builder->read(
            fn () => throw new Exception("old write"),
            fn () => throw new Exception("new write"),
        );
        $this->builder->trackErrors(true);

        /** @var Migrator */
        $migrator = $this->builder->build()->value;
        $migrator->read($stage->value, LDContext::create('user-key'), Stage::LIVE);

        $events = $this->eventProcessor->getEvents();

        $this->assertCount(2, $events);

        $event = $events[1]; // First event is evaluation result
        $errors = $event['measurements'][1]; // First measurement is invoked

        $this->assertEquals('error', $errors['key']);
        foreach ($origins as $origin) {
            $this->assertTrue($errors['values'][$origin->value]);
        }
    }

    public function writeStageOriginProvider(): array
    {
        return [
            [Stage::OFF, [Origin::OLD]],
            [Stage::DUALWRITE, [Origin::OLD, Origin::NEW]],
            [Stage::SHADOW, [Origin::OLD, Origin::NEW]],
            [Stage::LIVE, [Origin::OLD, Origin::NEW]],
            [Stage::RAMPDOWN, [Origin::OLD, Origin::NEW]],
            [Stage::COMPLETE, [Origin::NEW]],
        ];
    }

    /**
     * @dataProvider writeStageOriginProvider
     */
    public function testTrackingInvokedForWrites(Stage $stage, array $origins): void
    {
        /** @var Migrator */
        $migrator = $this->builder->build()->value;
        $migrator->write($stage->value, LDContext::create('user-key'), Stage::LIVE);

        $events = $this->eventProcessor->getEvents();

        $this->assertCount(2, $events);

        $event = $events[1]; // First event is evaluation result
        $invoked = $event['measurements'][0];

        $this->assertEquals('invoked', $invoked['key']);

        array_map(fn ($origin) => $this->assertTrue($invoked['values'][$origin->value]), $origins);
    }

    /**
     * @dataProvider writeStageOriginProvider
     */
    public function testTrackingLatencyForWrites(Stage $stage, array $origins): void
    {
        $delayed = function (mixed $payload): Result {
            return Result::success(null);
        };

        $this->builder->write($delayed, $delayed);
        $this->builder->trackLatency(true);

        /** @var Migrator */
        $migrator = $this->builder->build()->value;
        $migrator->write($stage->value, LDContext::create('user-key'), Stage::LIVE);

        $events = $this->eventProcessor->getEvents();

        $this->assertCount(2, $events);

        $event = $events[1]; // First event is evaluation result
        $latencies = $event['measurements'][1]; // First measurement is invoked

        $this->assertEquals('latency_ms', $latencies['key']);
        foreach ($origins as $origin) {
            $this->assertGreaterThanOrEqual($latencies['values'][$origin->value], 100);
        }
    }

    public function authoritativeWriteStageOriginProvider(): array
    {
        return [
            [Stage::OFF, Origin::OLD],
            [Stage::DUALWRITE, Origin::OLD],
            [Stage::SHADOW, Origin::OLD],
            [Stage::LIVE, Origin::NEW],
            [Stage::RAMPDOWN, Origin::NEW],
            [Stage::COMPLETE, Origin::NEW],
        ];
    }

    /**
     * @dataProvider authoritativeWriteStageOriginProvider
     */
    public function testTrackingErrorsForAuthoritativeWrites(Stage $stage, Origin $origin): void
    {
        $this->builder->write(
            fn () => throw new Exception("old write"),
            fn () => throw new Exception("new write"),
        );
        $this->builder->trackErrors(true);

        /** @var Migrator */
        $migrator = $this->builder->build()->value;
        $migrator->write($stage->value, LDContext::create('user-key'), Stage::LIVE);

        $events = $this->eventProcessor->getEvents();

        $this->assertCount(2, $events);

        $event = $events[1]; // First event is evaluation result
        $errors = $event['measurements'][1]; // First measurement is invoked

        $this->assertEquals('error', $errors['key']);
        $this->assertTrue($errors['values'][$origin->value]);
    }

    public function nonauthoritativeWriteStageOriginProvider(): array
    {
        return [
            // Off and Complete only run authoritative writes so there is nothing to test
            [Stage::DUALWRITE, Origin::OLD, Origin::NEW],
            [Stage::SHADOW, Origin::OLD, Origin::NEW],
            [Stage::LIVE, Origin::NEW, Origin::OLD],
            [Stage::RAMPDOWN, Origin::NEW, Origin::OLD],
        ];
    }

    /**
     * @dataProvider nonauthoritativeWriteStageOriginProvider
     */
    public function testTrackingErrorsForNonAuthoritativeWrites(Stage $stage, Origin $authoritative, Origin $nonauthoritative): void
    {
        if ($authoritative == Origin::OLD) {
            $this->builder->write(
                fn () => Result::success(null),
                fn () => throw new Exception("new write"),
            );
        } else {
            $this->builder->write(
                fn () => throw new Exception("old write"),
                fn () => Result::success(null),
            );
        }
        $this->builder->trackErrors(true);

        /** @var Migrator */
        $migrator = $this->builder->build()->value;
        $migrator->write($stage->value, LDContext::create('user-key'), Stage::LIVE);

        $events = $this->eventProcessor->getEvents();

        $this->assertCount(2, $events);

        $event = $events[1]; // First event is evaluation result
        $errors = $event['measurements'][1]; // First measurement is invoked

        $this->assertEquals('error', $errors['key']);
        $this->assertTrue($errors['values'][$nonauthoritative->value]);
    }

    public function trackingConsistencyProvider(): array
    {
        return [
            // SHADOW and LIVE are the only stages that run both reads and as a
            // result, can produce consistency values.
            [Stage::SHADOW, "same", "same", true],
            [Stage::LIVE, "same", "same", true],

            [Stage::SHADOW, "different", "same", false],
            [Stage::LIVE, "different", "same", false],
        ];
    }

    /**
     * @dataProvider trackingConsistencyProvider
     */
    public function testTrackingConsistency(Stage $stage, string $old, string $new, bool $shouldMatch): void
    {
        $this->builder->read(
            fn () => Result::success($old),
            fn () => Result::success($new),
            fn ($lhs, $rhs) => $lhs == $rhs,
        );

        /** @var Migrator */
        $migrator = $this->builder->build()->value;
        $migrator->read($stage->value, LDContext::create('user-key'), Stage::LIVE);

        $events = $this->eventProcessor->getEvents();

        $this->assertCount(2, $events);

        $event = $events[1]; // First event is evaluation result
        $consistency = $event['measurements'][1]; // First measurement is invoked

        $this->assertEquals('consistent', $consistency['key']);
        $this->assertEquals($shouldMatch, $consistency['value']);

        // TODO(sc-219378): Add sampling tests
    }

    public function readHandlerExceptionProvider(): array
    {
        return [
            [Stage::OFF, "old read"],
            [Stage::DUALWRITE, "old read"],
            [Stage::SHADOW, "old read"],
            [Stage::LIVE, "new read"],
            [Stage::RAMPDOWN, "new read"],
            [Stage::COMPLETE, "new read"],
        ];
    }

    /**
     * @dataProvider readHandlerExceptionProvider
     */
    public function testReadsHandleExceptionsFromMigrationFunctions(Stage $stage, string $expectedError): void
    {
        $this->builder->read(
            fn () => throw new Exception("old read"),
            fn () => throw new Exception("new read"),
        );

        /** @var Migrator */
        $migrator = $this->builder->build()->value;

        $result = $migrator->read($stage->value, LDContext::create('user-key'), Stage::LIVE);

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals($expectedError, $result->error);
    }

    public function authoritativeWriteHandlerExceptionProvider(): array
    {
        return [
            [Stage::OFF, "old write"],
            [Stage::DUALWRITE, "old write"],
            [Stage::SHADOW, "old write"],
            [Stage::LIVE, "new write"],
            [Stage::RAMPDOWN, "new write"],
            [Stage::COMPLETE, "new write"],
        ];
    }

    /**
     * @dataProvider authoritativeWriteHandlerExceptionProvider
     */
    public function testHandlesExceptionsFromAuthoritativeWrite(Stage $stage, string $expectedError): void
    {
        $this->builder->write(
            fn () => throw new Exception("old write"),
            fn () => throw new Exception("new write"),
        );

        /** @var Migrator */
        $migrator = $this->builder->build()->value;

        $result = $migrator->write($stage->value, LDContext::create('user-key'), Stage::LIVE);

        $this->assertFalse($result->authoritative->isSuccessful());
        $this->assertNull($result->nonauthoritative);
        $this->assertEquals($expectedError, $result->authoritative->error);
    }

    public function nonauthoritativeWriteHandlerExceptionProvider(): array
    {
        return [
            // Off and Complete only run authoritative writes so there is nothing to test
            [Stage::DUALWRITE, "new write", true],
            [Stage::SHADOW, "new write", true],
            [Stage::LIVE, "old write", false],
            [Stage::RAMPDOWN, "old write", false],
        ];
    }

    /**
     * @dataProvider nonauthoritativeWriteHandlerExceptionProvider
     */
    public function testHandlesExceptionsFromNonAuthoritativeWrite(Stage $stage, string $expectedError, bool $oldIsAuthoritative): void
    {
        $success = fn () => Result::success(null);

        if ($oldIsAuthoritative) {
            $this->builder->write(
                $success,
                fn () => throw new Exception("new write"),
            );
        } else {
            $this->builder->write(
                fn () => throw new Exception("old write"),
                $success,
            );
        }

        /** @var Migrator */
        $migrator = $this->builder->build()->value;

        $result = $migrator->write($stage->value, LDContext::create('user-key'), Stage::LIVE);

        $this->assertTrue($result->authoritative->isSuccessful());
        $this->assertFalse($result->nonauthoritative?->isSuccessful());
        $this->assertEquals($expectedError, $result->nonauthoritative?->error);
    }
}
