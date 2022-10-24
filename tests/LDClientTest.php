<?php

namespace LaunchDarkly\Tests;

use InvalidArgumentException;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\LDClient;
use LaunchDarkly\LDContext;
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

    private function makeOffFlagWithValue($key, $value)
    {
        return ModelBuilders::flagBuilder($key)
            ->version(100)
            ->on(false)
            ->variations('FALLTHROUGH', $value)
            ->fallthroughVariation(0)
            ->offVariation(1)
            ->build();
    }

    private function makeFlagThatEvaluatesToNull($key)
    {
        return ModelBuilders::flagBuilder($key)
            ->version(100)
            ->on(false)
            ->variations('none')
            ->fallthroughVariation(0)
            ->build();
    }

    private function makeClient($overrideOptions = [])
    {
        $options = [
            'feature_requester' => $this->mockRequester,
            'event_processor' => new MockEventProcessor()
        ];
        $x = array_merge($options, $overrideOptions);
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
        $flag = $this->makeOffFlagWithValue('flagkey', 'flagvalue');
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
        $user = LDContext::create("Message");
        $this->assertEquals("aa747c502a898200f9e4fa21bac68136f886a0e27aec70ba06daf2e2a5cb5597", $client->secureModeHash($user));
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
}
