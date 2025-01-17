<?php

namespace LaunchDarkly\Tests;

use InvalidArgumentException;
use LaunchDarkly\BigSegmentsEvaluationStatus;
use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\LDClient;
use LaunchDarkly\LDContext;
use LaunchDarkly\Migrations\Operation;
use LaunchDarkly\Migrations\OpTracker;
use LaunchDarkly\Migrations\Origin;
use LaunchDarkly\Migrations\Stage;
use LaunchDarkly\Subsystems\BigSegmentStatusListener;
use LaunchDarkly\Tests\Impl\Evaluation\EvaluatorTestUtil;
use LaunchDarkly\Types\BigSegmentsConfig;
use LaunchDarkly\Types\BigSegmentsStoreMetadata;
use LaunchDarkly\Types\BigSegmentsStoreStatus;
use Psr\Log\LoggerInterface;

class LDClientTest extends \PHPUnit\Framework\TestCase
{
    private MockFeatureRequester $mockRequester;

    public function setUp(): void
    {
        $this->mockRequester = new MockFeatureRequester();
    }

    public function testDefaultCtor()
    {
        $this->assertInstanceOf(LDClient::class, new LDClient("BOGUS_SDK_KEY"));
    }
    /**
     * @param mixed $key
     * @param mixed $value
     * @param mixed $samplingRatio
     * @param mixed $excludeFromSummaries
     */
    private function makeOffFlagWithValue($key, $value, $samplingRatio = 1, $excludeFromSummaries = false)
    {
        return ModelBuilders::flagBuilder($key)
            ->version(100)
            ->on(false)
            ->variations('FALLTHROUGH', $value)
            ->fallthroughVariation(0)
            ->offVariation(1)
            ->samplingRatio($samplingRatio)
            ->excludeFromSummaries($excludeFromSummaries)
            ->build();
    }
    /**
     * @param mixed $key
     */
    private function makeFlagThatEvaluatesToNull($key)
    {
        return ModelBuilders::flagBuilder($key)
            ->version(100)
            ->on(false)
            ->variations('none')
            ->fallthroughVariation(0)
            ->build();
    }
    /**
     * @param mixed $overrideOptions
     */
    private function makeClient($overrideOptions = []): LDClient
    {
        $options = [
            'feature_requester' => $this->mockRequester,
            'event_processor' => new MockEventProcessor()
        ];
        return new LDClient("someKey", array_merge($options, $overrideOptions));
    }

    public function testVariationReturnsFlagValue()
    {
        $flag = $this->makeOffFlagWithValue('feature', 'value');
        $this->mockRequester->addFlag($flag);
        $client = $this->makeClient();

        $value = $client->variation('feature', LDContext::create('userkey'), 'default');
        $this->assertEquals('value', $value);
    }

    public function testVariationDetailReturnsFlagValue()
    {
        $flag = $this->makeOffFlagWithValue('feature', 'value');
        $this->mockRequester->addFlag($flag);
        $client = $this->makeClient();

        $detail = $client->variationDetail('feature', LDContext::create('userkey'), 'default');
        $this->assertEquals('value', $detail->getValue());
        $this->assertFalse($detail->isDefaultValue());
        $this->assertEquals(1, $detail->getVariationIndex());
        $this->assertEquals(EvaluationReason::off(), $detail->getReason());
    }

    public function testVariationReturnsDefaultIfFlagEvaluatesToNull()
    {
        $flag = $this->makeFlagThatEvaluatesToNull('feature');
        $this->mockRequester->addFlag($flag);
        $client = $this->makeClient();

        $value = $client->variation('feature', LDContext::create('userkey'), 'default');
        $this->assertEquals('default', $value);
    }

    public function testVariationDetailReturnsDefaultIfFlagEvaluatesToNull()
    {
        $flag = $this->makeFlagThatEvaluatesToNull('feature');
        $this->mockRequester->addFlag($flag);
        $client = $this->makeClient();

        $detail = $client->variationDetail('feature', LDContext::create('userkey'), 'default');
        $this->assertEquals('default', $detail->getValue());
        $this->assertTrue($detail->isDefaultValue());
        $this->assertNull($detail->getVariationIndex());
        $this->assertEquals(EvaluationReason::off(), $detail->getReason());
    }

    public function testVariationReturnsDefaultForUnknownFlag()
    {
        $this->mockRequester->expectQueryForUnknownFlag('foo');
        $client = $this->makeClient();

        $this->assertEquals('argdef', $client->variation('foo', LDContext::create('userkey'), 'argdef'));
    }

    public function testVariationDetailReturnsDefaultForUnknownFlag()
    {
        $this->mockRequester->expectQueryForUnknownFlag('foo');
        $client = $this->makeClient();

        $detail = $client->variationDetail('foo', LDContext::create('userkey'), 'default');
        $this->assertEquals('default', $detail->getValue());
        $this->assertTrue($detail->isDefaultValue());
        $this->assertNull($detail->getVariationIndex());
        $this->assertEquals(EvaluationReason::error(EvaluationReason::FLAG_NOT_FOUND_ERROR), $detail->getReason());
    }

    public function testVariationReturnsDefaultFromConfigurationForUnknownFlag()
    {
        $this->mockRequester->expectQueryForUnknownFlag('foo');
        $client = $this->makeClient(['defaults' => ['foo' => 'fromarray']]);

        $this->assertEquals('fromarray', $client->variation('foo', LDContext::create('userkey'), 'argdef'));
    }

    public function testVariationPassesContextToEvaluator()
    {
        $flag = ModelBuilders::booleanFlagWithClauses(ModelBuilders::clause('kind1', 'attr1', 'in', 'value1'));
        $this->mockRequester->addFlag($flag);
        $client = $this->makeClient();

        $context = LDContext::builder('key')->kind('kind1')->set('attr1', 'value1')->build();
        $this->assertTrue($client->variation($flag->getKey(), $context, false));
    }

    public function testVariationSendsEvent()
    {
        $flag = $this->makeOffFlagWithValue('flagkey', 'flagvalue');
        $this->mockRequester->addFlag($flag);
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $context = LDContext::create('userkey');
        $client->variation('flagkey', $context, 'default');
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertEquals('flagkey', $event['key']);
        $this->assertEquals($flag->getVersion(), $event['version']);
        $this->assertEquals('flagvalue', $event['value']);
        $this->assertEquals(1, $event['variation']);
        $this->assertEquals($context, $event['context']);
        $this->assertEquals('default', $event['default']);
        $this->assertFalse(isset($event['trackEvents']));
        $this->assertFalse(isset($event['reason']));
    }

    public function testVariationDetailSendsEvent()
    {
        $flag = $this->makeOffFlagWithValue('flagkey', 'flagvalue', 1);
        $this->mockRequester->addFlag($flag);
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $context = LDContext::create('userkey');
        $client->variationDetail('flagkey', $context, 'default');
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertEquals('flagkey', $event['key']);
        $this->assertEquals($flag->getVersion(), $event['version']);
        $this->assertEquals('flagvalue', $event['value']);
        $this->assertEquals(1, $event['variation']);
        $this->assertEquals($context, $event['context']);
        $this->assertEquals('default', $event['default']);
        $this->assertFalse(isset($event['trackEvents']));
        $this->assertEquals(['kind' => 'OFF'], $event['reason']);
    }

    public function testZeroSamplingRatioSuppressesFeatureEvent()
    {
        $flag = $this->makeOffFlagWithValue('flagkey', 'flagvalue', 0);
        $this->mockRequester->addFlag($flag);

        $mockPublisher = new MockEventPublisher("", []);
        $options = [
            'feature_requester' => $this->mockRequester,
            'event_publisher' => $mockPublisher,
        ];
        $client = new LDClient("someKey", $options);

        $context = LDContext::create('userkey');
        $client->variationDetail('flagkey', $context, 'default');
        // We don't flush the event processor until __destruct is called. Let's
        // force that by unsetting this variable.
        unset($client);
        $this->assertCount(0, $mockPublisher->payloads);
    }

    public function testFeatureEventContainsExcludeFlagSummaryValue(): void
    {
        $flag = $this->makeOffFlagWithValue('flagkey', 'flagvalue', 1, true);
        $this->mockRequester->addFlag($flag);

        $mockPublisher = new MockEventPublisher("", []);
        $options = [
            'feature_requester' => $this->mockRequester,
            'event_publisher' => $mockPublisher,
        ];
        $client = new LDClient("someKey", $options);

        $context = LDContext::create('userkey');
        $client->variationDetail('flagkey', $context, 'default');
        // We don't flush the event processor until __destruct is called. Let's
        // force that by unsetting this variable.
        unset($client);

        $event = json_decode($mockPublisher->payloads[0], true)[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertTrue($event['excludeFromSummaries']);
    }

    public function testMigrationVariationSendsEvent(): void
    {
        $flag = $this->makeOffFlagWithValue('flag', 'off', 1);
        $this->mockRequester->addFlag($flag);

        $detail = new EvaluationDetail('off', 0, EvaluationReason::fallthrough());
        $tracker = new OpTracker(
            EvaluatorTestUtil::testLogger(),
            'flag',
            $flag,
            LDContext::create('user-key'),
            $detail,
            Stage::LIVE,
        );
        $tracker->operation(Operation::READ)
            ->invoked(Origin::OLD)
            ->invoked(Origin::NEW);

        $mockPublisher = new MockEventPublisher("", []);
        $options = [
            'feature_requester' => $this->mockRequester,
            'event_publisher' => $mockPublisher,
        ];
        $client = new LDClient("someKey", $options);

        $client->trackMigrationOperation($tracker);
        // We don't flush the event processor until __destruct is called. Let's
        // force that by unsetting this variable.
        unset($client);

        $events = json_decode($mockPublisher->payloads[0], true);
        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertEquals('migration_op', $event['kind']);
        $this->assertEquals('flag', $event['evaluation']['key']);
        $this->assertArrayNotHasKey('samplingRatio', $event);
    }

    public function testMigrationVariationDoesNotSendEventWith0SamplingRatio()
    {
        $flag = $this->makeOffFlagWithValue('flag', 'off', 0);
        $this->mockRequester->addFlag($flag);

        $detail = new EvaluationDetail('off', 0, EvaluationReason::fallthrough());
        $tracker = new OpTracker(
            EvaluatorTestUtil::testLogger(),
            'flag',
            $flag,
            LDContext::create('user-key'),
            $detail,
            Stage::LIVE,
        );
        $tracker->operation(Operation::READ)
            ->invoked(Origin::OLD)
            ->invoked(Origin::NEW);

        $mockPublisher = new MockEventPublisher("", []);
        $options = [
            'feature_requester' => $this->mockRequester,
            'event_publisher' => $mockPublisher,
        ];
        $client = new LDClient("someKey", $options);

        $client->trackMigrationOperation($tracker);
        // We don't flush the event processor until __destruct is called. Let's
        // force that by unsetting this variable.
        unset($client);

        $this->assertCount(0, $mockPublisher->payloads);
    }

    public function testVariationForcesTrackingWhenMatchedRuleHasTrackEventsSet()
    {
        $flag = ModelBuilders::flagBuilder('flagkey')
            ->version(100)
            ->variations('fallthrough', 'flagvalue')
            ->on(true)
            ->fallthroughVariation(0)
            ->rule(
                ModelBuilders::flagRuleBuilder()
                    ->id('rule-id')
                    ->variation(1)
                    ->trackEvents(true)
                    ->clause(ModelBuilders::clause(null, 'key', 'in', 'userkey'))
                    ->build()
            )
            ->build();

        $this->mockRequester->addFlag($flag);
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $context = LDContext::create('userkey');
        $client->variation('flagkey', $context, 'default');
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertEquals('flagkey', $event['key']);
        $this->assertEquals($flag->getVersion(), $event['version']);
        $this->assertEquals('flagvalue', $event['value']);
        $this->assertEquals(1, $event['variation']);
        $this->assertEquals($context, $event['context']);
        $this->assertEquals('default', $event['default']);
        $this->assertTrue($event['trackEvents']);
        $this->assertEquals(['kind' => 'RULE_MATCH', 'ruleIndex' => 0, 'ruleId' => 'rule-id'], $event['reason']);
    }

    public function testVariationForcesTrackingForFallthroughWhenTrackEventsFallthroughIsSet()
    {
        $flag = ModelBuilders::flagBuilder('flagkey')
            ->version(100)
            ->variations('fellthrough', 'flagvalue')
            ->on(true)
            ->offVariation(1)
            ->fallthroughVariation(0)
            ->trackEventsFallthrough(true)
            ->build();

        $this->mockRequester->addFlag($flag);
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $context = LDContext::create('userkey');
        $client->variation('flagkey', $context, 'default');
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertEquals('flagkey', $event['key']);
        $this->assertEquals($flag->getVersion(), $event['version']);
        $this->assertEquals('fellthrough', $event['value']);
        $this->assertEquals(0, $event['variation']);
        $this->assertEquals($context, $event['context']);
        $this->assertEquals('default', $event['default']);
        $this->assertTrue($event['trackEvents']);
        $this->assertEquals(['kind' => 'FALLTHROUGH'], $event['reason']);
    }

    public function testVariationSendsEventForUnknownFlag()
    {
        $this->mockRequester->expectQueryForUnknownFlag('flagkey');
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $context = LDContext::create('userkey');
        $client->variation('flagkey', $context, 'default');
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertEquals('flagkey', $event['key']);
        $this->assertFalse(isset($event['version']));
        $this->assertEquals('default', $event['value']);
        $this->assertFalse(isset($event['variation']));
        $this->assertEquals($context, $event['context']);
        $this->assertEquals('default', $event['default']);
        $this->assertFalse(isset($event['reason']));
    }

    public function testVariationDetailSendsEventForUnknownFlag()
    {
        $this->mockRequester->expectQueryForUnknownFlag('flagkey');
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $context = LDContext::create('userkey');
        $client->variationDetail('flagkey', $context, 'default');
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertEquals('flagkey', $event['key']);
        $this->assertFalse(isset($event['version']));
        $this->assertEquals('default', $event['value']);
        $this->assertFalse(isset($event['variation']));
        $this->assertEquals($context, $event['context']);
        $this->assertEquals('default', $event['default']);
        $this->assertEquals(['kind' => 'ERROR', 'errorKind' => 'FLAG_NOT_FOUND'], $event['reason']);
    }

    public function testAllFlagsStateReturnsState()
    {
        $flag = ModelBuilders::flagBuilder('feature')
            ->version(100)
            ->on(false)
            ->variations('fall', 'off', 'on')
            ->offVariation(1)
            ->fallthroughVariation(0)
            ->trackEvents(true)
            ->debugEventsUntilDate(1000)
            ->build();

        $this->mockRequester->addFlag($flag);
        $client = $this->makeClient();

        $context = LDContext::create('userkey');
        $state = $client->allFlagsState($context);

        $this->assertTrue($state->isValid());
        $this->assertEquals(['feature' => 'off'], $state->toValuesMap());
        $expectedState = [
            'feature' => 'off',
            '$flagsState' => [
                'feature' => [
                    'variation' => 1,
                    'version' => 100,
                    'trackEvents' => true,
                    'debugEventsUntilDate' => 1000
                ]
            ],
            '$valid' => true
        ];
        $this->assertEquals($expectedState, $state->jsonSerialize());
    }

    public function testAllFlagsStateHandlesExperimentationReasons()
    {
        $flag = ModelBuilders::flagBuilder('feature')
            ->version(100)
            ->on(true)
            ->variations('fall', 'off', 'on')
            ->offVariation(1)
            ->fallthroughVariation(0)
            ->trackEventsFallthrough(true)
            ->debugEventsUntilDate(1000)
            ->build();

        $this->mockRequester->addFlag($flag);
        $client = $this->makeClient();

        $context = LDContext::create('userkey');
        $state = $client->allFlagsState($context);

        $this->assertTrue($state->isValid());
        $this->assertEquals(['feature' => 'fall'], $state->toValuesMap());
        $expectedState = [
            'feature' => 'fall',
            '$flagsState' => [
                'feature' => [
                    'variation' => 0,
                    'version' => 100,
                    'trackEvents' => true,
                    'trackReason' => true,
                    'debugEventsUntilDate' => 1000,
                    'reason' => [
                        'kind' => 'FALLTHROUGH',
                    ],
                ]
            ],
            '$valid' => true
        ];
        $this->assertEquals($expectedState, $state->jsonSerialize());
    }

    public function testAllFlagsStateReturnsStateWithReasons()
    {
        $flag = ModelBuilders::flagBuilder('feature')
            ->version(100)
            ->on(false)
            ->variations('fall', 'off', 'on')
            ->offVariation(1)
            ->fallthroughVariation(0)
            ->trackEvents(true)
            ->debugEventsUntilDate(1000)
            ->build();

        $this->mockRequester->addFlag($flag);
        $client = $this->makeClient();

        $context = LDContext::create('userkey');
        $state = $client->allFlagsState($context, ['withReasons' => true]);

        $this->assertTrue($state->isValid());
        $this->assertEquals(['feature' => 'off'], $state->toValuesMap());
        $expectedState = [
            'feature' => 'off',
            '$flagsState' => [
                'feature' => [
                    'variation' => 1,
                    'version' => 100,
                    'trackEvents' => true,
                    'debugEventsUntilDate' => 1000,
                    'reason' => ['kind' => 'OFF']
                ]
            ],
            '$valid' => true
        ];
        $this->assertEquals($expectedState, $state->jsonSerialize());
    }

    public function testAllFlagsStateCanFilterForClientSideFlags()
    {
        $flagJson = ['key' => 'server-side-1', 'version' => 1, 'on' => false, 'salt' => '', 'deleted' => false,
            'targets' => [], 'rules' => [], 'prerequisites' => [], 'fallthrough' => [],
            'offVariation' => 0, 'variations' => ['a'], 'clientSide' => false];
        $flag1 = FeatureFlag::decode($flagJson);
        $flagJson['key'] = 'server-side-2';
        $flag2 = FeatureFlag::decode($flagJson);
        $flagJson['key'] = 'client-side-1';
        $flagJson['clientSide'] = true;
        $flagJson['variations'] = ['value1'];
        $flag3 = FeatureFlag::decode($flagJson);
        $flagJson['key'] = 'client-side-2';
        $flagJson['variations'] = ['value2'];
        $flag4 = FeatureFlag::decode($flagJson);
        $this->mockRequester->addFlag($flag1)->addFlag($flag2)->addFlag($flag3)->addFlag($flag4);
        $client = $this->makeClient();

        $context = LDContext::create('userkey');
        $state = $client->allFlagsState($context, ['clientSideOnly' => true]);

        $this->assertTrue($state->isValid());
        $this->assertEquals(['client-side-1' => 'value1', 'client-side-2' => 'value2'], $state->toValuesMap());
    }

    public function testAllFlagsStateCanOmitDetailsForUntrackedFlags()
    {
        $flag1 = ModelBuilders::flagBuilder('flag1')
            ->version(100)
            ->variations('value1')
            ->on(false)
            ->offVariation(0)
            ->build();
        $flag2 = ModelBuilders::flagBuilder('flag2')
            ->version(200)
            ->variations('value2')
            ->on(false)
            ->offVariation(0)
            ->trackEvents(true)
            ->build();
        $flag3 = ModelBuilders::flagBuilder('flag3')
            ->version(300)
            ->variations('value3')
            ->on(false)
            ->offVariation(0)
            ->debugEventsUntilDate(1000)
            ->build();

        $this->mockRequester->addFlag($flag1)->addFlag($flag2)->addFlag($flag3);
        $client = $this->makeClient();

        $context = LDContext::create('userkey');
        $state = $client->allFlagsState($context, ['withReasons' => true, 'detailsOnlyForTrackedFlags' => true]);

        $this->assertTrue($state->isValid());
        $this->assertEquals(['flag1' => 'value1', 'flag2' => 'value2', 'flag3' => 'value3'], $state->toValuesMap());
        $expectedState = [
            'flag1' => 'value1',
            'flag2' => 'value2',
            'flag3' => 'value3',
            '$flagsState' => [
                'flag1' => [
                    'variation' => 0,
                ],
                'flag2' => [
                    'variation' =>  0,
                    'version' => 200,
                    'reason' => ['kind' => 'OFF'],
                    'trackEvents' => true
                ],
                'flag3' => [
                    'variation' => 0,
                    'version' => 300,
                    'reason' => ['kind' => 'OFF'],
                    'debugEventsUntilDate' => 1000
                ]
            ],
            '$valid' => true
        ];
        $this->assertEquals($expectedState, $state->jsonSerialize());
    }

    public function testAllFlagsShowsTopLevelPrereqKeys()
    {
        $topLevel1 = ModelBuilders::flagBuilder('top-level-1')
            ->version(100)
            ->on(true)
            ->variations(true, false)
            ->fallthroughVariation(0)
            ->prerequisite('prereq1-of-tl1', 0)
            ->prerequisite('prereq2-of-tl1', 0)
            ->build();

        $prereq1OfTl1 = ModelBuilders::flagBuilder('prereq1-of-tl1')
            ->version(200)
            ->on(true)
            ->variations(true, false)
            ->fallthroughVariation(0)
            ->prerequisite('prereq1-of-prereq1', 0)
            ->build();

        $prereq2OfTl1 = ModelBuilders::flagBuilder('prereq2-of-tl1')
            ->version(200)
            ->on(true)
            ->variations(true, false)
            ->fallthroughVariation(0)
            ->build();

        $prereq1OfPrereq1 = ModelBuilders::flagBuilder('prereq1-of-prereq1')
            ->version(300)
            ->on(true)
            ->variations(true, false)
            ->fallthroughVariation(0)
            ->build();

        $this->mockRequester->addFlag($topLevel1);
        $this->mockRequester->addFlag($prereq1OfTl1);
        $this->mockRequester->addFlag($prereq2OfTl1);
        $this->mockRequester->addFlag($prereq1OfPrereq1);

        $client = $this->makeClient();

        $context = LDContext::create('userkey');
        $state = $client->allFlagsState($context);

        $this->assertTrue($state->isValid());
        $this->assertEquals([
            'top-level-1' => true,
            'prereq1-of-tl1' => true,
            'prereq2-of-tl1' => true,
            'prereq1-of-prereq1' => true,
        ], $state->toValuesMap());

        $expectedState = [
            'top-level-1' => true,
            'prereq1-of-tl1' => true,
            'prereq2-of-tl1' => true,
            'prereq1-of-prereq1' => true,
            '$flagsState' => [
                'top-level-1' => [
                    'variation' => 0,
                    'version' => 100,
                    'prerequisites' => ['prereq1-of-tl1', 'prereq2-of-tl1'],
                ],
                'prereq1-of-tl1' => [
                    'variation' => 0,
                    'version' => 200,
                    'prerequisites' => ['prereq1-of-prereq1'],
                ],
                'prereq2-of-tl1' => [
                    'variation' => 0,
                    'version' => 200,
                ],
                'prereq1-of-prereq1' => [
                    'variation' => 0,
                    'version' => 300,
                ]
            ],
            '$valid' => true
        ];
        $this->assertEquals($expectedState, $state->jsonSerialize());
    }

    public function testAllFlagsPrereqsAreHaltedOnFailure()
    {
        $topLevel1 = ModelBuilders::flagBuilder('top-level-1')
            ->version(100)
            ->on(true)
            ->variations(true, false)
            ->fallthroughVariation(0)
            // This flag will actually evaluate with variation index 0
            ->prerequisite('prereq1-of-tl1', 1)
            ->prerequisite('prereq2-of-tl1', 0)
            ->build();

        $prereq1OfTl1 = ModelBuilders::flagBuilder('prereq1-of-tl1')
            ->version(200)
            ->on(true)
            ->variations(true, false)
            ->fallthroughVariation(0)
            ->build();

        $prereq2OfTl1 = ModelBuilders::flagBuilder('prereq2-of-tl1')
            ->version(200)
            ->on(true)
            ->variations(true, false)
            ->fallthroughVariation(0)
            ->build();

        $this->mockRequester->addFlag($topLevel1);
        $this->mockRequester->addFlag($prereq1OfTl1);
        $this->mockRequester->addFlag($prereq2OfTl1);

        $client = $this->makeClient();

        $context = LDContext::create('userkey');
        $state = $client->allFlagsState($context);

        $this->assertTrue($state->isValid());
        $this->assertEquals([
            'top-level-1' => null,
            'prereq1-of-tl1' => true,
            'prereq2-of-tl1' => true,
        ], $state->toValuesMap());

        $expectedState = [
            'top-level-1' => null,
            'prereq1-of-tl1' => true,
            'prereq2-of-tl1' => true,
            '$flagsState' => [
                'top-level-1' => [
                    'version' => 100,
                    'prerequisites' => ['prereq1-of-tl1'],
                ],
                'prereq1-of-tl1' => [
                    'variation' => 0,
                    'version' => 200,
                ],
                'prereq2-of-tl1' => [
                    'variation' => 0,
                    'version' => 200,
                ],
            ],
            '$valid' => true
        ];
        $this->assertEquals($expectedState, $state->jsonSerialize());
    }

    public function testAllFlagsClientSideVisiblityDoesNotAffectPrereqKeyList()
    {
        $flagJson = [
            'key' => 'top-level-1', 'version' => 100, 'on' => true, 'salt' => '', 'deleted' => false,
            'targets' => [], 'rules' => [], 'prerequisites' => [['key' => 'prereq1-of-tl1', 'variation' => 0], ['key' => 'prereq2-of-tl1', 'variation' => 0]], 'fallthrough' => ['variation' => 0],
            'offVariation' => 1, 'variations' => ['a', 'b'], 'clientSide' => true
        ];
        $topLevel1 = FeatureFlag::decode($flagJson);

        $flagJson['key'] = 'prereq1-of-tl1';
        $flagJson['prerequisites'] = [];
        $flagJson['version'] = 200;
        $prereq1OfTl1 = FeatureFlag::decode($flagJson);

        $flagJson['key'] = 'prereq2-of-tl1';
        $flagJson['clientSide'] = false;
        $prereq2OfTl1 = FeatureFlag::decode($flagJson);

        $this->mockRequester->addFlag($topLevel1);
        $this->mockRequester->addFlag($prereq1OfTl1);
        $this->mockRequester->addFlag($prereq2OfTl1);

        $client = $this->makeClient();

        $context = LDContext::create('userkey');
        $state = $client->allFlagsState($context, ['clientSideOnly' => true]);

        $this->assertTrue($state->isValid());
        $this->assertEquals([
            'top-level-1' => 'a',
            'prereq1-of-tl1' => 'a',
        ], $state->toValuesMap());

        $expectedState = [
            'top-level-1' => 'a',
            'prereq1-of-tl1' => 'a',
            '$flagsState' => [
                'top-level-1' => [
                    'variation' => 0,
                    'version' => 100,
                    'prerequisites' => ['prereq1-of-tl1', 'prereq2-of-tl1'],
                ],
                'prereq1-of-tl1' => [
                    'variation' => 0,
                    'version' => 200,
                ],
            ],
            '$valid' => true
        ];
        $this->assertEquals($expectedState, $state->jsonSerialize());
    }

    public function testIdentifySendsEvent()
    {
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $context = LDContext::create('userkey');
        $client->identify($context);
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('identify', $event['kind']);
        $this->assertEquals($context, $event['context']);
    }

    public function testTrackSendsEvent()
    {
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $context = LDContext::create('userkey');
        $client->track('eventkey', $context);
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('custom', $event['kind']);
        $this->assertEquals('eventkey', $event['key']);
        $this->assertEquals($context, $event['context']);
        $this->assertFalse(isset($event['data']));
        $this->assertFalse(isset($event['metricValue']));
    }

    public function testTrackSendsEventWithData()
    {
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);
        $data = ['thing' => 'stuff'];

        $context = LDContext::create('userkey');
        $client->track('eventkey', $context, $data);
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('custom', $event['kind']);
        $this->assertEquals('eventkey', $event['key']);
        $this->assertEquals($context, $event['context']);
        $this->assertEquals($data, $event['data']);
        $this->assertFalse(isset($event['metricValue']));
    }

    public function testTrackSendsEventWithDataAndMetricValue()
    {
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);
        $data = ['thing' => 'stuff'];
        $metricValue = 1.5;

        $context = LDContext::create('userkey');
        $client->track('eventkey', $context, $data, $metricValue);
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('custom', $event['kind']);
        $this->assertEquals('eventkey', $event['key']);
        $this->assertEquals($context, $event['context']);
        $this->assertEquals($data, $event['data']);
        $this->assertEquals($metricValue, $event['metricValue']);
    }

    public function testEventsAreNotPublishedIfSendEventsIsFalse()
    {
        // In order to do this test, we cannot provide a mock object for Event_Processor_,
        // because if we do that, it won't bother even looking at the send_events flag.
        // Instead, we need to just put in a mock Event_Publisher_, so that the default
        // EventProcessor would forward events to it if send_events were not disabled.
        $mockPublisher = new MockEventPublisher("", []);
        $options = [
            'feature_requester' => $this->mockRequester,
            'event_publisher' => $mockPublisher,
            'send_events' => false,
        ];
        $client = new LDClient("someKey", $options);
        $client->track('eventkey', LDContext::create('userkey'));

        // We don't flush the event processor until __destruct is called. Let's
        // force that by unsetting this variable.
        unset($client);
        $this->assertEquals([], $mockPublisher->payloads);
    }

    public function testOnlyValidFeatureRequester()
    {
        $this->expectException(InvalidArgumentException::class);
        new LDClient("BOGUS_SDK_KEY", ['feature_requester' => \stdClass::class]);
    }

    public function testSecureModeHash()
    {
        $client = new LDClient("secret", ['offline' => true]);
        $context = LDContext::create("Message");
        $expected = "aa747c502a898200f9e4fa21bac68136f886a0e27aec70ba06daf2e2a5cb5597";
        $this->assertEquals($expected, $client->secureModeHash($context));
    }

    public function testLoggerInterfaceWarn()
    {
        // Use LoggerInterface impl, instead of concrete Logger from Monolog, to demonstrate the problem with `warn`.
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger->expects(self::atLeastOnce())->method('warning');

        $client = new LDClient('secret', [
            'logger' => $logger,
            'offline' => true
        ]);

        $invalidContext = LDContext::create('');

        $client->variation('MyFeature', $invalidContext);
    }

    public function testUsesDefaultIfFlagIsNotFound(): void
    {
        $client = $this->makeClient();
        $result = $client->migrationVariation('unknown-flag-key', LDContext::create('userkey'), Stage::LIVE);

        $this->assertEquals(Stage::LIVE, $result['stage']);
        $this->assertInstanceOf(OpTracker::class, $result['tracker']);
    }

    public function testUsesDefaultIfFlagReturnsInvalidStage(): void
    {
        $flag = $this->makeOffFlagWithValue('feature', 'invalid stage value');
        $this->mockRequester->addFlag($flag);
        $client = $this->makeClient();

        $result = $client->migrationVariation('feature', LDContext::create('userkey'), Stage::LIVE);

        $this->assertEquals(Stage::LIVE, $result['stage']);
        $this->assertInstanceOf(OpTracker::class, $result['tracker']);
    }

    public function stageProvider(): array
    {
        return [
            [Stage::OFF],
            [Stage::DUALWRITE],
            [Stage::SHADOW],
            [Stage::LIVE],
            [Stage::RAMPDOWN],
            [Stage::COMPLETE],
        ];
    }

    /**
     * @dataProvider stageProvider
     */
    public function testCanDetermineCorrectStage(Stage $stage): void
    {
        $flag = $this->makeOffFlagWithValue('feature', $stage->value);
        $this->mockRequester->addFlag($flag);
        $client = $this->makeClient();

        $result = $client->migrationVariation('feature', LDContext::create('userkey'), Stage::OFF);

        $this->assertEquals($stage, $result['stage']);
        $this->assertInstanceOf(OpTracker::class, $result['tracker']);
    }

    public function testCanCheckBigSegmentStatus(): void
    {
        $store = new BigSegmentsStoreImpl([
            new BigSegmentsStoreMetadata(lastUpToDate: 0),
        ], []);

        $config = new BigSegmentsConfig(store: $store);
        $client = $this->makeClient(['big_segments' => $config]);
        $provider = $client->getBigSegmentStatusProvider();

        $status = $provider->status();
        $this->assertTrue($status->isAvailable());
        $this->assertTrue($status->isStale());
    }

    public function testCanCheckBigSegmentStatusWhenUnconfigured(): void
    {
        $client = $this->makeClient();
        $provider = $client->getBigSegmentStatusProvider();

        $status = $provider->status();
        $this->assertFalse($status->isAvailable());
        $this->assertFalse($status->isStale());
    }

    public function testEachCheckCausesALookup(): void
    {
        $store = new BigSegmentsStoreImpl([
            new BigSegmentsStoreMetadata(lastUpToDate: 0),
            new BigSegmentsStoreMetadata(lastUpToDate: time()),
        ], []);

        $config = new BigSegmentsConfig(store: $store);
        $client = $this->makeClient(['big_segments' => $config]);
        $provider = $client->getBigSegmentStatusProvider();

        $status = $provider->status();
        $this->assertTrue($status->isAvailable());
        $this->assertTrue($status->isStale());

        $status = $provider->status();
        $this->assertTrue($status->isAvailable());
        $this->assertFalse($status->isStale());
    }

    public function testCanControlFreshnessThroughConfig(): void
    {
        $now = time();
        $store = new BigSegmentsStoreImpl([
            new BigSegmentsStoreMetadata(lastUpToDate: $now - 100),
            new BigSegmentsStoreMetadata(lastUpToDate: $now - 1_000),
        ], []);

        $config = new BigSegmentsConfig(store: $store, staleAfter: 500);
        $client = $this->makeClient(['big_segments' => $config]);
        $provider = $client->getBigSegmentStatusProvider();

        $status = $provider->status();
        $this->assertFalse($status->isStale());

        $status = $provider->status();
        $this->assertTrue($status->isStale());
    }

    public function testReportsUnconfiguredBigSegmentsEvaluation(): void
    {
        $segment = ModelBuilders::segmentBuilder('test')
            ->generation(100)
            ->unbounded(true)
            ->build();
        $flag = ModelBuilders::booleanFlagWithClauses(
            ModelBuilders::clauseMatchingSegment($segment)
        );
        $this->mockRequester->addFlag($flag);
        $this->mockRequester->addSegment($segment);

        $client = $this->makeClient();
        $detail = $client->variationDetail($flag->getKey(), LDContext::create('userkey'), false);

        $this->assertEquals(BigSegmentsEvaluationStatus::NOT_CONFIGURED, $detail->getReason()->bigSegmentsEvaluationStatus());
    }

    public function testReportsHealthyBigSegmentsEvaluationStatus(): void
    {
        $segment = ModelBuilders::segmentBuilder('test')
            ->generation(100)
            ->unbounded(true)
            ->build();
        $flag = ModelBuilders::booleanFlagWithClauses(
            ModelBuilders::clauseMatchingSegment($segment)
        );
        $this->mockRequester->addFlag($flag);
        $this->mockRequester->addSegment($segment);

        $store = new BigSegmentsStoreImpl([
            new BigSegmentsStoreMetadata(lastUpToDate: time()),
        ], []);

        $config = new BigSegmentsConfig(store: $store);
        $client = $this->makeClient(['big_segments' => $config]);
        $detail = $client->variationDetail($flag->getKey(), LDContext::create('userkey'), false);

        $this->assertEquals(BigSegmentsEvaluationStatus::HEALTHY, $detail->getReason()->bigSegmentsEvaluationStatus());
    }

    public function testReportsStaleBigSegmentsEvaluationStatus(): void
    {
        $segment = ModelBuilders::segmentBuilder('test')
            ->generation(100)
            ->unbounded(true)
            ->build();
        $flag = ModelBuilders::booleanFlagWithClauses(
            ModelBuilders::clauseMatchingSegment($segment)
        );
        $this->mockRequester->addFlag($flag);
        $this->mockRequester->addSegment($segment);

        $store = new BigSegmentsStoreImpl([
            new BigSegmentsStoreMetadata(lastUpToDate: time() - 1_000),
        ], []);

        $config = new BigSegmentsConfig(store: $store, staleAfter: 100);
        $client = $this->makeClient(['big_segments' => $config]);
        $detail = $client->variationDetail($flag->getKey(), LDContext::create('userkey'), false);

        $this->assertEquals(BigSegmentsEvaluationStatus::STALE, $detail->getReason()->bigSegmentsEvaluationStatus());
    }

    public function testCheckingBigSegmentStatusPreventsEvaluationFromNeedingTo(): void
    {
        $segment = ModelBuilders::segmentBuilder('test')
            ->generation(100)
            ->unbounded(true)
            ->build();
        $flag = ModelBuilders::booleanFlagWithClauses(
            ModelBuilders::clauseMatchingSegment($segment)
        );
        $this->mockRequester->addFlag($flag);
        $this->mockRequester->addSegment($segment);

        $store = new BigSegmentsStoreImpl([
            new BigSegmentsStoreMetadata(lastUpToDate: time()),
            new BigSegmentsStoreMetadata(lastUpToDate: time() - 1000),
        ], []);

        $config = new BigSegmentsConfig(store: $store, staleAfter: 500);
        $client = $this->makeClient(['big_segments' => $config]);
        $provider = $client->getBigSegmentStatusProvider();

        $status = $provider->status();
        $this->assertTrue($status->isAvailable());
        $this->assertFalse($status->isStale());

        // Should be STALE if it actually made a new request. However, it isn't
        // configured to make another status check that fast, so it uses what
        // it last knew, which is that it isn't stale.
        $detail = $client->variationDetail($flag->getKey(), LDContext::create('userkey'), false);
        $this->assertEquals(BigSegmentsEvaluationStatus::HEALTHY, $detail->getReason()->bigSegmentsEvaluationStatus());

        $status = $provider->status();
        $this->assertTrue($status->isAvailable());
        $this->assertTrue($status->isStale());
    }

    public function testBigSegmentStatusListeners(): void
    {
        $segment = ModelBuilders::segmentBuilder('test')
            ->generation(100)
            ->unbounded(true)
            ->build();
        $flag = ModelBuilders::booleanFlagWithClauses(
            ModelBuilders::clauseMatchingSegment($segment)
        );
        $this->mockRequester->addFlag($flag);
        $this->mockRequester->addSegment($segment);

        $now = time();
        $store = new BigSegmentsStoreImpl([
            new BigSegmentsStoreMetadata(lastUpToDate: $now),
            new BigSegmentsStoreMetadata(lastUpToDate: $now - 1000),
            new BigSegmentsStoreMetadata(lastUpToDate: $now),
        ], []);

        $config = new BigSegmentsConfig(store: $store, staleAfter: 500);
        $client = $this->makeClient(['big_segments' => $config]);
        $provider = $client->getBigSegmentStatusProvider();

        $subjects = [];
        $listener = new MockBigSegmentStatusListener(
            function (?BigSegmentsStoreStatus $old, BigSegmentsStoreStatus $new) use (&$subjects) {
                $subjects[] = ['old' => $old, 'new' => $new];
            }
        );
        $provider->attach($listener);

        // Triggers a status lookup
        $client->variationDetail($flag->getKey(), LDContext::create('userkey'), false);

        // Force 2 more
        $provider->status();
        $provider->status();

        $this->assertCount(3, $subjects);

        $old = $subjects[0]['old'];
        $new = $subjects[0]['new'];
        $this->assertNull($old);
        $this->assertFalse($new->isStale());

        $old = $subjects[1]['old'];
        $new = $subjects[1]['new'];
        $this->assertFalse($old->isStale());
        $this->assertTrue($new->isStale());

        $old = $subjects[2]['old'];
        $new = $subjects[2]['new'];
        $this->assertTrue($old->isStale());
        $this->assertFalse($new->isStale());
    }

    public function testBigSegmentStatusListenerExceptionsDoNotHaltException(): void
    {
        $now = time();
        $store = new BigSegmentsStoreImpl([
            new BigSegmentsStoreMetadata(lastUpToDate: $now),
        ], []);

        $config = new BigSegmentsConfig(store: $store, staleAfter: 500);
        $client = $this->makeClient(['big_segments' => $config]);
        $provider = $client->getBigSegmentStatusProvider();

        $listener = new MockBigSegmentStatusListener(fn () => throw new \Exception('oops'));
        $provider->attach($listener);

        try {
            $provider->status();
        } catch (\Exception) {
            $this->fail('The SDK should have swallowed the exception.');
        }

        $this->assertTrue(true, 'confirming that we did in fact get this far');
    }
}

class MockBigSegmentStatusListener implements BigSegmentStatusListener
{
    private $fn;

    /**
     * @param callable(BigSegmentsStoreStatus, BigSegmentsStoreStatus): void $fn
     */
    public function __construct(callable $fn)
    {
        $this->fn = $fn;
    }

    public function statusChanged(?BigSegmentsStoreStatus $old, BigSegmentsStoreStatus $new): void
    {
        ($this->fn)($old, $new);
    }
}
