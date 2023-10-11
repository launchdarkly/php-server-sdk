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

    public function setUp(): void
    {
        $requester = new MockFeatureRequester();

        foreach (Stage::cases() as $stage) {
            $requester->addFlag($this->makeOffFlagWithValue($stage->value, $stage->value));
        }

        $options = [
            'feature_requester' => $requester,
            'event_processor' => new MockEventProcessor()
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
        $this->markTestSkipped('skipped until sc-219378');
    }

    /**
     * @dataProvider readStageOriginProvider
     */
    public function testTrackingLatencyForReads(Stage $stage, array $origins): void
    {
        $this->markTestSkipped('skipped until sc-219378');
    }

    /**
     * @dataProvider readStageOriginProvider
     */
    public function testTrackingErrorsForReads(Stage $stage, array $origins): void
    {
        $this->markTestSkipped('skipped until sc-219378');
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
        $this->markTestSkipped('skipped until sc-219377');
    }

    /**
     * @dataProvider writeStageOriginProvider
     */
    public function testTrackingLatencyForWrites(Stage $stage, array $origins): void
    {
        $this->markTestSkipped('skipped until sc-219377');
    }

    /**
     * @dataProvider writeStageOriginProvider
     */
    public function testTrackingErrorsForWrites(Stage $stage, array $origins): void
    {
        $this->markTestSkipped('skipped until sc-219377');
    }

    public function testTrackingConsistency(): void
    {
        $this->markTestSkipped('skipped until sc-219377');
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
