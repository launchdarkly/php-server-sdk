<?php

namespace LaunchDarkly\Tests;

use InvalidArgumentException;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\LDClient;
use LaunchDarkly\LDUser;
use LaunchDarkly\LDUserBuilder;
use Psr\Log\LoggerInterface;

class LDClientTest extends \PHPUnit\Framework\TestCase
{
    public function testDefaultCtor()
    {
        $this->assertInstanceOf(LDClient::class, new LDClient("BOGUS_SDK_KEY"));
    }

    private function makeOffFlagWithValue($key, $value)
    {
        $flagJson = [
            'key' => $key,
            'version' => 100,
            'deleted' => false,
            'on' => false,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['FALLTHROUGH', $value],
            'salt' => ''
        ];
        return FeatureFlag::decode($flagJson);
    }

    private function makeFlagThatEvaluatesToNull($key)
    {
        $flagJson = [
            'key' => $key,
            'version' => 100,
            'deleted' => false,
            'on' => false,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => null,
            'fallthrough' => ['variation' => 0],
            'variations' => ['none'],
            'salt' => ''
        ];
        return FeatureFlag::decode($flagJson);
    }

    private function makeClient($overrideOptions = [])
    {
        $options = [
            'feature_requester_class' => MockFeatureRequester::class,
            'event_processor' => new MockEventProcessor()
        ];
        return new LDClient("someKey", array_merge($options, $overrideOptions));
    }

    public function testVariationReturnsFlagValue()
    {
        $flag = $this->makeOffFlagWithValue('feature', 'value');
        MockFeatureRequester::$flags = ['feature' => $flag];
        $client = $this->makeClient();

        $value = $client->variation('feature', new LDUser('userkey'), 'default');
        $this->assertEquals('value', $value);
    }

    public function testVariationDetailReturnsFlagValue()
    {
        $flag = $this->makeOffFlagWithValue('feature', 'value');
        MockFeatureRequester::$flags = ['feature' => $flag];
        $client = $this->makeClient();

        $detail = $client->variationDetail('feature', new LDUser('userkey'), 'default');
        $this->assertEquals('value', $detail->getValue());
        $this->assertFalse($detail->isDefaultValue());
        $this->assertEquals(1, $detail->getVariationIndex());
        $this->assertEquals(EvaluationReason::off(), $detail->getReason());
    }

    public function testVariationReturnsDefaultIfFlagEvaluatesToNull()
    {
        $flag = $this->makeFlagThatEvaluatesToNull('feature');
        MockFeatureRequester::$flags = ['feature' => $flag];
        $client = $this->makeClient();

        $value = $client->variation('feature', new LDUser('userkey'), 'default');
        $this->assertEquals('default', $value);
    }

    public function testVariationDetailReturnsDefaultIfFlagEvaluatesToNull()
    {
        $flag = $this->makeFlagThatEvaluatesToNull('feature');
        MockFeatureRequester::$flags = ['feature' => $flag];
        $client = $this->makeClient();

        $detail = $client->variationDetail('feature', new LDUser('userkey'), 'default');
        $this->assertEquals('default', $detail->getValue());
        $this->assertTrue($detail->isDefaultValue());
        $this->assertNull($detail->getVariationIndex());
        $this->assertEquals(EvaluationReason::off(), $detail->getReason());
    }

    public function testVariationReturnsDefaultForUnknownFlag()
    {
        MockFeatureRequester::$flags = [];
        $client = $this->makeClient();

        $this->assertEquals('argdef', $client->variation('foo', new LDUser('userkey'), 'argdef'));
    }

    public function testVariationDetailReturnsDefaultForUnknownFlag()
    {
        MockFeatureRequester::$flags = [];
        $client = $this->makeClient();

        $detail = $client->variationDetail('foo', new LDUser('userkey'), 'default');
        $this->assertEquals('default', $detail->getValue());
        $this->assertTrue($detail->isDefaultValue());
        $this->assertNull($detail->getVariationIndex());
        $this->assertEquals(EvaluationReason::error(EvaluationReason::FLAG_NOT_FOUND_ERROR), $detail->getReason());
    }

    public function testVariationReturnsDefaultFromConfigurationForUnknownFlag()
    {
        MockFeatureRequester::$flags = [];
        $client = $this->makeClient(['defaults' => ['foo' => 'fromarray']]);

        $this->assertEquals('fromarray', $client->variation('foo', new LDUser('userkey'), 'argdef'));
    }

    public function testVariationSendsEvent()
    {
        $flag = $this->makeOffFlagWithValue('flagkey', 'flagvalue');
        MockFeatureRequester::$flags = ['flagkey' => $flag];
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $user = new LDUser('userkey');
        $client->variation('flagkey', new LDUser('userkey'), 'default');
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertEquals('flagkey', $event['key']);
        $this->assertEquals($flag->getVersion(), $event['version']);
        $this->assertEquals('flagvalue', $event['value']);
        $this->assertEquals(1, $event['variation']);
        $this->assertEquals($user, $event['user']);
        $this->assertEquals('default', $event['default']);
        $this->assertFalse(isset($event['trackEvents']));
        $this->assertFalse(isset($event['reason']));
    }

    public function testVariationDetailSendsEvent()
    {
        $flag = $this->makeOffFlagWithValue('flagkey', 'flagvalue');
        MockFeatureRequester::$flags = ['flagkey' => $flag];
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $user = new LDUser('userkey');
        $client->variationDetail('flagkey', $user, 'default');
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertEquals('flagkey', $event['key']);
        $this->assertEquals($flag->getVersion(), $event['version']);
        $this->assertEquals('flagvalue', $event['value']);
        $this->assertEquals(1, $event['variation']);
        $this->assertEquals($user, $event['user']);
        $this->assertEquals('default', $event['default']);
        $this->assertFalse(isset($event['trackEvents']));
        $this->assertEquals(['kind' => 'OFF'], $event['reason']);
    }

    public function testVariationForcesTrackingWhenMatchedRuleHasTrackEventsSet()
    {
        $flagJson = [
            'key' => 'flagkey',
            'version' => 100,
            'deleted' => false,
            'on' => true,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [
                [
                    'clauses' => [
                        [
                            'attribute' => 'key',
                            'op' => 'in',
                            'values' => ['userkey'],
                            'negate' => false
                        ]
                    ],
                    'id' => 'rule-id',
                    'variation' => 1,
                    'trackEvents' => true
                ]
            ],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fellthrough', 'flagvalue'],
            'salt' => ''
        ];
        $flag = FeatureFlag::decode($flagJson);

        MockFeatureRequester::$flags = ['flagkey' => $flag];
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $user = new LDUser('userkey');
        $client->variation('flagkey', new LDUser('userkey'), 'default');
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertEquals('flagkey', $event['key']);
        $this->assertEquals($flag->getVersion(), $event['version']);
        $this->assertEquals('flagvalue', $event['value']);
        $this->assertEquals(1, $event['variation']);
        $this->assertEquals($user, $event['user']);
        $this->assertEquals('default', $event['default']);
        $this->assertTrue($event['trackEvents']);
        $this->assertEquals(['kind' => 'RULE_MATCH', 'ruleIndex' => 0, 'ruleId' => 'rule-id'], $event['reason']);
    }

    public function testVariationForcesTrackingForFallthroughWhenTrackEventsFallthroughIsSet()
    {
        $flagJson = [
            'key' => 'flagkey',
            'version' => 100,
            'deleted' => false,
            'on' => true,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fellthrough', 'flagvalue'],
            'salt' => '',
            'trackEventsFallthrough' => true
        ];
        $flag = FeatureFlag::decode($flagJson);

        MockFeatureRequester::$flags = ['flagkey' => $flag];
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $user = new LDUser('userkey');
        $client->variation('flagkey', new LDUser('userkey'), 'default');
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertEquals('flagkey', $event['key']);
        $this->assertEquals($flag->getVersion(), $event['version']);
        $this->assertEquals('fellthrough', $event['value']);
        $this->assertEquals(0, $event['variation']);
        $this->assertEquals($user, $event['user']);
        $this->assertEquals('default', $event['default']);
        $this->assertTrue($event['trackEvents']);
        $this->assertEquals(['kind' => 'FALLTHROUGH'], $event['reason']);
    }

    public function testVariationSendsEventForUnknownFlag()
    {
        MockFeatureRequester::$flags = [];
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $user = new LDUser('userkey');
        $client->variation('flagkey', new LDUser('userkey'), 'default');
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertEquals('flagkey', $event['key']);
        $this->assertFalse(isset($event['version']));
        $this->assertEquals('default', $event['value']);
        $this->assertFalse(isset($event['variation']));
        $this->assertEquals($user, $event['user']);
        $this->assertEquals('default', $event['default']);
        $this->assertFalse(isset($event['reason']));
    }

    public function testVariationDetailSendsEventForUnknownFlag()
    {
        MockFeatureRequester::$flags = [];
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $user = new LDUser('userkey');
        $client->variationDetail('flagkey', new LDUser('userkey'), 'default');
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('feature', $event['kind']);
        $this->assertEquals('flagkey', $event['key']);
        $this->assertFalse(isset($event['version']));
        $this->assertEquals('default', $event['value']);
        $this->assertFalse(isset($event['variation']));
        $this->assertEquals($user, $event['user']);
        $this->assertEquals('default', $event['default']);
        $this->assertEquals(['kind' => 'ERROR', 'errorKind' => 'FLAG_NOT_FOUND'], $event['reason']);
    }


    public function testVariationWithAnonymousUserSendsEventWithAnonymousContextKind()
    {
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $flag = $this->makeOffFlagWithValue('feature', 'value');

        $anon_builder = new LDUserBuilder("anon@email.com");
        $anon = $anon_builder->anonymous(true)->build();

        $client->variation('feature', $anon, 'default');

        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));

        $event = $queue[0];

        $this->assertEquals('anonymousUser', $event['contextKind']);
    }

    public function testAllFlagsStateReturnsState()
    {
        $flagJson = [
            'key' => 'feature',
            'version' => 100,
            'deleted' => false,
            'on' => false,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fall', 'off', 'on'],
            'salt' => '',
            'trackEvents' => true,
            'debugEventsUntilDate' => 1000
        ];
        $flag = FeatureFlag::decode($flagJson);

        MockFeatureRequester::$flags = ['feature' => $flag];
        $client = $this->makeClient();

        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $state = $client->allFlagsState($user);

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
        $flagJson = [
            'key' => 'feature',
            'version' => 100,
            'deleted' => false,
            'on' => true,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fall', 'off', 'on'],
            'salt' => '',
            'trackEvents' => false,
            'trackEventsFallthrough' => true,
            'debugEventsUntilDate' => 1000
        ];
        $flag = FeatureFlag::decode($flagJson);

        MockFeatureRequester::$flags = ['feature' => $flag];
        $client = $this->makeClient();

        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $state = $client->allFlagsState($user);

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
        $flagJson = [
            'key' => 'feature',
            'version' => 100,
            'deleted' => false,
            'on' => false,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fall', 'off', 'on'],
            'salt' => '',
            'trackEvents' => true,
            'debugEventsUntilDate' => 1000
        ];
        $flag = FeatureFlag::decode($flagJson);

        MockFeatureRequester::$flags = ['feature' => $flag];
        $client = $this->makeClient();

        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $state = $client->allFlagsState($user, ['withReasons' => true]);

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
        MockFeatureRequester::$flags = [
            $flag1->getKey() => $flag1, $flag2->getKey() => $flag2, $flag3->getKey() => $flag3, $flag4->getKey() => $flag4
        ];
        $client = $this->makeClient();

        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $state = $client->allFlagsState($user, ['clientSideOnly' => true]);

        $this->assertTrue($state->isValid());
        $this->assertEquals(['client-side-1' => 'value1', 'client-side-2' => 'value2'], $state->toValuesMap());
    }

    public function testAllFlagsStateCanOmitDetailsForUntrackedFlags()
    {
        $flag1Json = [
            'key' => 'flag1',
            'version' => 100,
            'deleted' => false,
            'on' => false,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 0,
            'fallthrough' => null,
            'variations' => ['value1'],
            'salt' => '',
            'trackEvents' => false
        ];
        $flag2Json = [
            'key' => 'flag2',
            'version' => 200,
            'deleted' => false,
            'on' => false,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 0,
            'fallthrough' => null,
            'variations' => ['value2'],
            'salt' => '',
            'trackEvents' => true
        ];
        $flag3Json = [
            'key' => 'flag3',
            'version' => 300,
            'deleted' => false,
            'on' => false,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 0,
            'fallthrough' => null,
            'variations' => ['value3'],
            'salt' => '',
            'trackEvents' => false,
            'debugEventsUntilDate' => 1000
        ];
        $flag1 = FeatureFlag::decode($flag1Json);
        $flag2 = FeatureFlag::decode($flag2Json);
        $flag3 = FeatureFlag::decode($flag3Json);

        MockFeatureRequester::$flags = ['flag1' => $flag1, 'flag2' => $flag2, 'flag3' => $flag3];
        $client = $this->makeClient();

        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $state = $client->allFlagsState($user, ['withReasons' => true, 'detailsOnlyForTrackedFlags' => true]);

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

        $user = new LDUser('userkey');
        $client->track('eventkey', $user);
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('custom', $event['kind']);
        $this->assertEquals('eventkey', $event['key']);
        $this->assertEquals($user, $event['user']);
        $this->assertFalse(isset($event['data']));
        $this->assertFalse(isset($event['metricValue']));
    }

    public function testTrackSendsEventWithData()
    {
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);
        $data = ['thing' => 'stuff'];

        $user = new LDUser('userkey');
        $client->track('eventkey', $user, $data);
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('custom', $event['kind']);
        $this->assertEquals('eventkey', $event['key']);
        $this->assertEquals($user, $event['user']);
        $this->assertEquals($data, $event['data']);
        $this->assertFalse(isset($event['metricValue']));
    }

    public function testTrackSendsEventWithDataAndMetricValue()
    {
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);
        $data = ['thing' => 'stuff'];
        $metricValue = 1.5;

        $user = new LDUser('userkey');
        $client->track('eventkey', $user, $data, $metricValue);
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('custom', $event['kind']);
        $this->assertEquals('eventkey', $event['key']);
        $this->assertEquals($user, $event['user']);
        $this->assertEquals($data, $event['data']);
        $this->assertEquals($metricValue, $event['metricValue']);
    }

    public function testTrackWithAnonymousUserSendsEventWithAnonymousContextKind()
    {
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $anon_builder = new LDUserBuilder("anon@email.com");
        $anon = $anon_builder->anonymous(true)->build();

        $client->track('eventkey', $anon);
        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));
        $event = $queue[0];
        $this->assertEquals('custom', $event['kind']);
        $this->assertEquals('anonymousUser', $event['contextKind']);
    }

    public function testAliasEventsAreCorrect()
    {
        $ep = new MockEventProcessor();
        $client = $this->makeClient(['event_processor' => $ep]);

        $user_builder = new LDUserBuilder("user@email.com");
        $user = $user_builder->anonymous(false)->build();
        $anon_builder = new LDUserBuilder("anon@email.com");
        $anon = $anon_builder->anonymous(true)->build();

        $client->alias($user, $anon);

        $queue = $ep->getEvents();
        $this->assertEquals(1, sizeof($queue));

        $event = $queue[0];

        $this->assertEquals('alias', $event['kind']);
        $this->assertEquals($user->getKey(), $event['key']);
        $this->assertEquals('user', $event['contextKind']);
        $this->assertEquals($anon->getKey(), $event['previousKey']);
        $this->assertEquals('anonymousUser', $event['previousContextKind']);
    }

    public function testEventsAreNotPublishedIfSendEventsIsFalse()
    {
        // In order to do this test, we cannot provide a mock object for Event_Processor_,
        // because if we do that, it won't bother even looking at the send_events flag.
        // Instead, we need to just put in a mock Event_Publisher_, so that the default
        // EventProcessor would forward events to it if send_events were not disabled.
        $mockPublisher = new MockEventPublisher("", []);
        $options = [
            'feature_requester_class' => MockFeatureRequester::class,
            'event_publisher' => $mockPublisher,
            'send_events' => false,
        ];
        $client = new LDClient("someKey", $options);
        $client->track('eventkey', new LDUser('userkey'));

        // We don't flush the event processor until __destruct is called. Let's
        // force that by unsetting this variable.
        unset($client);
        $this->assertEquals([], $mockPublisher->payloads);
    }

    public function testOnlyValidFeatureRequester()
    {
        $this->expectException(InvalidArgumentException::class);
        new LDClient("BOGUS_SDK_KEY", ['feature_requester_class' => \stdClass::class]);
    }

    public function testSecureModeHash()
    {
        $client = new LDClient("secret", ['offline' => true]);
        $user = new LDUser("Message");
        $this->assertEquals("aa747c502a898200f9e4fa21bac68136f886a0e27aec70ba06daf2e2a5cb5597", $client->secureModeHash($user));
    }

    public function testLoggerInterfaceWarn()
    {
        // Use LoggerInterface impl, instead of concrete Logger from Monolog, to demonstrate the problem with `warn`.
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger->expects(self::atLeastOnce())->method('warning');

        $client = new LDClient('secret', [
            'logger' => $logger,
        ]);

        $user = new LDUser('');

        $client->variation('MyFeature', $user);
    }
}
