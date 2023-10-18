<?php

namespace LaunchDarkly\Tests\Migrations;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Integrations\TestData\FlagBuilder;
use LaunchDarkly\Integrations\TestData\MigrationSettingsBuilder;
use LaunchDarkly\LDContext;
use LaunchDarkly\Migrations\Operation;
use LaunchDarkly\Migrations\OpTracker;
use LaunchDarkly\Migrations\Origin;
use LaunchDarkly\Migrations\Stage;
use LaunchDarkly\Tests\Impl\Evaluation\EvaluatorTestUtil;

class OpTrackerTest extends \PHPUnit\Framework\TestCase
{
    protected OpTracker $tracker;

    protected function setUp(): void
    {
        $flag = (new FlagBuilder('flag'))->variations('off')->variationForAll(0)->build(0);
        $detail = new EvaluationDetail('off', 0, EvaluationReason::fallthrough());
        $this->tracker = new OpTracker(
            EvaluatorTestUtil::testLogger(),
            'flag',
            FeatureFlag::decode($flag),
            LDContext::create('user-key'),
            $detail,
            Stage::LIVE,
        );
    }

    protected function setTrackerDefaults(): void
    {
        $this->tracker->operation(Operation::READ)
            ->invoked(Origin::OLD)
            ->invoked(Origin::NEW);
    }

    public function testCanBuildSuccessfully(): void
    {
        $this->setTrackerDefaults();
        $result = $this->tracker->build();

        $this->assertIsArray($result, 'tracker failed to build event');
        $this->assertEquals('migration_op', $result['kind']);
        $this->assertEquals('read', $result['operation']);
        $this->assertSame(['user' => 'user-key'], $result['contextKeys']);

        $evaluation = $result['evaluation'];
        $this->assertEquals('flag', $evaluation['key']);
        $this->assertEquals('off', $evaluation['value']);
        $this->assertEquals('live', $evaluation['default']);
        $this->assertEquals(0, $evaluation['version']);
        $this->assertEquals(0, $evaluation['variation']);
        $this->assertEquals('FALLTHROUGH', $evaluation['reason']['kind']);
    }

    public function testFailsWithoutOperation(): void
    {
        $this->tracker->invoked(Origin::OLD);
        $result = $this->tracker->build();

        $this->assertEquals('operation not provided', $result);
    }

    public function testFailsWithoutInvocations(): void
    {
        $this->tracker->operation(Operation::READ);
        $result = $this->tracker->build();

        $this->assertEquals('no origins were invoked', $result);
    }

    public function testFailsWithInvalidContext(): void
    {
        $flag = (new FlagBuilder('flag'))->variations('off')->variationForAll(0)->build(0);
        $detail = new EvaluationDetail('off', 0, EvaluationReason::fallthrough());
        $tracker = new OpTracker(
            EvaluatorTestUtil::testLogger(),
            'flag',
            FeatureFlag::decode($flag),
            LDContext::create(''),
            $detail,
            Stage::OFF,
        );
        $tracker->operation(Operation::READ)
            ->invoked(Origin::OLD)
            ->invoked(Origin::NEW);
        $result = $tracker->build();

        $this->assertEquals('provided context was invalid', $result);
    }

    public function originProvider(): array
    {
        return [
            [Origin::OLD],
            [Origin::NEW],
        ];
    }

    public function originMismatchProvider(): array
    {
        return [
            [Origin::OLD, Origin::NEW],
            [Origin::NEW, Origin::OLD],
        ];
    }

    /**
     *  @dataProvider originMismatchProvider
     */
    public function testLatencyInvokedMismatch(Origin $invoked, Origin $recorded): void
    {
        $this->tracker->operation(Operation::WRITE);
        $this->tracker->invoked($invoked);
        $this->tracker->latency($recorded, 10);

        $error = $this->tracker->build();

        $this->assertEquals("provided latency for origin {$recorded->value} without recording invocation", $error);
    }

    /**
     *  @dataProvider originMismatchProvider
     */
    public function testErrorInvokedMismatch(Origin $invoked, Origin $recorded): void
    {
        $this->tracker->operation(Operation::WRITE);
        $this->tracker->invoked($invoked);
        $this->tracker->error($recorded);

        $error = $this->tracker->build();

        $this->assertEquals("provided error for origin {$recorded->value} without recording invocation", $error);
    }

    /**
     *  @dataProvider originProvider
     */
    public function testConsistencyInvokedMismatch(Origin $invoked): void
    {
        $this->tracker->operation(Operation::WRITE);
        $this->tracker->invoked($invoked);
        $this->tracker->consistent(fn () => true);

        $error = $this->tracker->build();

        $this->assertEquals("provided consistency without recording both invocations", $error);
    }

    /**
     *  @dataProvider originProvider
     */
    public function testCanTrackInvocationsIndividually(Origin $invoked): void
    {
        $this->tracker->operation(Operation::WRITE);
        $this->tracker->invoked($invoked);
        $event = $this->tracker->build();

        $this->assertCount(1, $event['measurements']);

        $measurement = $event['measurements'][0];
        $this->assertEquals('invoked', $measurement['key']);
        $this->assertCount(1, $measurement['values']);
        $this->assertTrue($measurement['values'][$invoked->value]);
    }

    public function testCanTrackBothInvocations(): void
    {
        $this->tracker->operation(Operation::WRITE);
        $this->tracker->invoked(Origin::OLD);
        $this->tracker->invoked(Origin::NEW);
        $event = $this->tracker->build();

        $this->assertCount(1, $event['measurements']);

        $measurement = $event['measurements'][0];
        $this->assertEquals('invoked', $measurement['key']);
        $this->assertCount(2, $measurement['values']);
        $this->assertTrue($measurement['values']['old']);
        $this->assertTrue($measurement['values']['new']);
    }

    /**
     *  @dataProvider originProvider
     */
    public function testCanTrackErrorsIndividually(Origin $invoked): void
    {
        $this->setTrackerDefaults();
        $this->tracker->error($invoked);
        $event = $this->tracker->build();

        $this->assertCount(2, $event['measurements']);

        $measurement = $event['measurements'][1]; // Skip invoked measurement

        $this->assertEquals('error', $measurement['key']);
        $this->assertCount(1, $measurement['values']);
        $this->assertTrue($measurement['values'][$invoked->value]);
    }

    public function testCanTrackBothErrors(): void
    {
        $this->setTrackerDefaults();
        $this->tracker->error(Origin::OLD);
        $this->tracker->error(Origin::NEW);
        $event = $this->tracker->build();

        $this->assertCount(2, $event['measurements']);

        $measurement = $event['measurements'][1]; // Skip invoked measurement

        $this->assertEquals('error', $measurement['key']);
        $this->assertCount(2, $measurement['values']);
        $this->assertTrue($measurement['values']['old']);
        $this->assertTrue($measurement['values']['new']);
    }

    /**
     *  @dataProvider originProvider
     */
    public function testCanTrackLatenciesIndividually(Origin $invoked): void
    {
        $this->setTrackerDefaults();
        $this->tracker->latency($invoked, 10);
        $event = $this->tracker->build();

        $this->assertCount(2, $event['measurements']);

        $measurement = $event['measurements'][1]; // Skip invoked measurement

        $this->assertEquals('latency_ms', $measurement['key']);
        $this->assertCount(1, $measurement['values']);
        $this->assertEquals(10, $measurement['values'][$invoked->value]);
    }

    public function testCanTrackBothLatencies(): void
    {
        $this->setTrackerDefaults();
        $this->tracker->latency(Origin::OLD, 10);
        $this->tracker->latency(Origin::NEW, 20);
        $event = $this->tracker->build();

        $this->assertCount(2, $event['measurements']);

        $measurement = $event['measurements'][1]; // Skip invoked measurement

        $this->assertEquals('latency_ms', $measurement['key']);
        $this->assertCount(2, $measurement['values']);
        $this->assertEquals(10, $measurement['values']['old']);
        $this->assertEquals(20, $measurement['values']['new']);
    }


    public function consistencyValuesProvider(): array
    {
        return [[true], [false]];
    }

    /**
     * @dataProvider consistencyValuesProvider
     */
    public function testWithoutCheckRatio(bool $consistent): void
    {
        $this->setTrackerDefaults();
        $this->tracker->consistent(fn () => $consistent);
        $event = $this->tracker->build();

        $this->assertCount(2, $event['measurements']);

        $measurement = $event['measurements'][1]; // Skip invoked measurement

        $this->assertEquals('consistent', $measurement['key']);
        $this->assertEquals($consistent, $measurement['value']);
    }

    /**
     * @dataProvider consistencyValuesProvider
     */
    public function testWithCheckRatioOf1(bool $consistent): void
    {
        $migrationSettings = (new MigrationSettingsBuilder())->setCheckRatio(1);
        $flag = (new FlagBuilder('flag'))->variations('off')->variationForAll(0)->migrationSettings($migrationSettings)->build(0);
        $detail = new EvaluationDetail('off', 0, EvaluationReason::fallthrough());
        $tracker = new OpTracker(
            EvaluatorTestUtil::testLogger(),
            'flag',
            FeatureFlag::decode($flag),
            LDContext::create('user-key'),
            $detail,
            Stage::LIVE,
        );
        $tracker->operation(Operation::READ)
            ->invoked(Origin::OLD)
            ->invoked(Origin::NEW);
        $tracker->consistent(fn () => $consistent);
        $event = $tracker->build();

        $this->assertCount(2, $event['measurements']);

        $measurement = $event['measurements'][1]; // Skip invoked measurement

        $this->assertEquals('consistent', $measurement['key']);
        $this->assertEquals($consistent, $measurement['value']);
        $this->assertArrayNotHasKey('samplingRatio', $measurement);
    }

    /**
     * @dataProvider consistencyValuesProvider
     */
    public function testCanDisableConsistencyWithCheckRatioOf0(bool $consistent): void
    {
        $migrationSettings = (new MigrationSettingsBuilder())->setCheckRatio(0);
        $flag = (new FlagBuilder('flag'))->variations('off')->variationForAll(0)->migrationSettings($migrationSettings)->build(0);
        $detail = new EvaluationDetail('off', 0, EvaluationReason::fallthrough());
        $tracker = new OpTracker(
            EvaluatorTestUtil::testLogger(),
            'flag',
            FeatureFlag::decode($flag),
            LDContext::create('user-key'),
            $detail,
            Stage::LIVE,
        );
        $tracker->operation(Operation::READ)
            ->invoked(Origin::OLD)
            ->invoked(Origin::NEW);
        $tracker->consistent(fn () => $consistent);
        $event = $tracker->build();

        $this->assertCount(1, $event['measurements']); // We always have invoked
    }
}
