<?php

namespace LaunchDarkly\Tests\Impl\Model;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\Events\EventFactory;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\Impl\Model\VariationOrRollout;
use LaunchDarkly\LDUser;
use LaunchDarkly\LDUserBuilder;
use LaunchDarkly\Tests\MockFeatureRequester;
use PHPUnit\Framework\TestCase;

const RULE_ID = 'ruleid';

$defaultUser = (new LDUserBuilder('foo'))->build();

function makeBooleanFlagWithRules(array $rules)
{
    $flagJson = [
        'key' => 'feature',
        'version' => 1,
        'deleted' => false,
        'on' => true,
        'targets' => [],
        'prerequisites' => [],
        'rules' => $rules,
        'offVariation' => 0,
        'fallthrough' => ['variation' => 0],
        'variations' => [false, true],
        'salt' => ''
    ];
    return FeatureFlag::decode($flagJson);
}

function makeBooleanFlagWithClauses($clauses)
{
    return makeBooleanFlagWithRules([['clauses' => $clauses, 'variation' => 1]]);
}

function makeRuleMatchingUser($user, $ruleAttrs = [])
{
    $clause = ['attribute' => 'key', 'op' => 'in', 'values' => [$user->getKey()], 'negate' => false];
    return array_merge(['id' => RULE_ID, 'clauses' => [$clause]], $ruleAttrs);
}

function makeSegmentMatchClause($segmentKey)
{
    return ['attribute' => '', 'op' => 'segmentMatch', 'values' => [$segmentKey], 'negate' => false];
}

// This is our way of verifying that the bucket value for a rollout is within 1.0 of the expected value.
function makeRolloutVariations($targetValue, $targetVariation, $otherVariation)
{
    return [
        ['weight' => $targetValue, 'variation' => $otherVariation],
        ['weight' => 1, 'variation' => $targetVariation],
        ['weight' => 100000 - ($targetValue + 1), 'variation' => $otherVariation]
    ];
}

class FeatureFlagTest extends TestCase
{
    private static $json1 = "{
  \"key\": \"integration.feature.0\",
  \"version\": 2210,
  \"on\": true,
  \"prerequisites\": [],
  \"salt\": \"aW50ZWdyYXRpb24uZmVhdHVyZS4w\",
  \"sel\": \"4be0f865f4554057b37ea81119dcd1c0\",
  \"targets\": [
    {
      \"values\": [
        \"e4bba312\",
        \"10959fa6\",
        \"43c5afd0\",
        \"1a49bd85\",
        \"c4a18457\",
        \"8e59168b\",
        \"b9c93848\"
      ],
      \"variation\": 0
    },
    {
      \"values\": [
        \"12fc100b\",
        \"e4bba312\",
        \"8735c005\",
        \"323e5049\",
        \"1a49bd85\",
        \"aa1cc27d\",
        \"c4a18457\",
        \"027e947f\",
        \"3f1387c7\",
        \"a9ae502f\"
      ],
      \"variation\": 1
    }
  ],
  \"rules\": [
    {
      \"variation\": 0,
      \"clauses\": [
        {
          \"attribute\": \"favoriteNumber\",
          \"op\": \"in\",
          \"values\": [],
          \"negate\": false
        }
      ]
    },
    {
      \"variation\": 1,
      \"clauses\": [
        {
          \"attribute\": \"favoriteNumber\",
          \"op\": \"in\",
          \"values\": [],
          \"negate\": false
        }
      ]
    },
    {
      \"variation\": 1,
      \"clauses\": [
        {
          \"attribute\": \"likesCats\",
          \"op\": \"in\",
          \"values\": [
            false
          ],
          \"negate\": false
        }
      ]
    }
  ],
  \"fallthrough\": {
    \"rollout\": {
      \"variations\": [
        {
          \"variation\": 0,
          \"weight\": 95000
        },
        {
          \"variation\": 1,
          \"weight\": 5000
        }
      ]
    }
  },
  \"offVariation\": null,
  \"variations\": [
    true,
    false
  ],
  \"deleted\": false
}";

    private static $json2 = "{
    \"key\": \"rollout.01.validFeatureKey\",
    \"version\": 717,
    \"on\": true,
    \"prerequisites\": [],
    \"salt\": \"02c72ed4bdf04a6193802596624b5e79\",
    \"sel\": \"7b3faaa9d5e24d0aa85dd806f567cf02\",
    \"targets\": [],
    \"rules\": [],
    \"fallthrough\": {
      \"rollout\": {
        \"variations\": [
          {
            \"variation\": 0,
            \"weight\": 50000
          },
          {
            \"variation\": 1,
            \"weight\": 40000
          },
          {
            \"variation\": 2,
            \"weight\": 10000
          }
        ]
      }
    },
    \"offVariation\": null,
    \"variations\": [
      \"ExpectedPrefix_A\",
      \"ExpectedPrefix_B\",
      \"ExpectedPrefix_C\"
    ],
    \"deleted\": false
  }";

    private static $eventFactory;
    private static $requester;

    public static function setUpBeforeClass(): void
    {
        static::$eventFactory = new EventFactory(false);
        static::$requester = new MockFeatureRequester();
    }

    public function testDecode()
    {
        $this->assertInstanceOf(FeatureFlag::class, FeatureFlag::decode(\GuzzleHttp\json_decode(FeatureFlagTest::$json1, true)));
        $this->assertInstanceOf(FeatureFlag::class, FeatureFlag::decode(\GuzzleHttp\json_decode(FeatureFlagTest::$json2, true)));
    }
    
    public function dataDecodeMulti()
    {
        return [
            'null-prerequisites' => [
                [
                    'key' => 'sysops-test',
                    'version' => 14,
                    'on' => true,
                    'prerequisites' => null,
                    'salt' => 'c3lzb3BzLXRlc3Q=',
                    'sel' => '8ed13de1bfb14507ba7e6dde01f3e035',
                    'targets' => [
                        [
                            'values' => [],
                            'variation' => 0,
                        ],
                        [
                            'values' => [],
                            'variation' => 1,
                        ],
                    ],
                    'rules' => [],
                    'fallthrough' => [
                        'variation' => 0,
                    ],
                    'offVariation' => null,
                    'variations' => [
                        true,
                        false,
                    ],
                    'deleted' => false,
                ]
            ],
        ];
    }
    
    /**
     * @dataProvider dataDecodeMulti
     * @param array $feature
     */
    public function testDecodeMulti(array $feature)
    {
        $featureFlag = FeatureFlag::decode($feature);
        
        self::assertInstanceOf(FeatureFlag::class, $featureFlag);
    }

    public function testFlagReturnsOffVariationIfFlagIsOff()
    {
        $flagJson = [
            'key' => 'feature',
            'version' => 1,
            'deleted' => false,
            'on' => false,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fall', 'off', 'on'],
            'salt' => ''
        ];
        $flag = FeatureFlag::decode($flagJson);

        $result = $flag->evaluate(new LDUser('user'), static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail('off', 1, EvaluationReason::off());
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testFlagReturnsNullIfFlagIsOffAndOffVariationIsUnspecified()
    {
        $flagJson = [
            'key' => 'feature',
            'version' => 1,
            'deleted' => false,
            'on' => false,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => null,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fall', 'off', 'on'],
            'salt' => ''
        ];
        $flag = FeatureFlag::decode($flagJson);

        $result = $flag->evaluate(new LDUser('user'), static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail(null, null, EvaluationReason::off());
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testFlagReturnsErrorIfOffVariationIsTooHigh()
    {
        $flagJson = [
            'key' => 'feature',
            'version' => 1,
            'deleted' => false,
            'on' => false,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 999,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fall', 'off', 'on'],
            'salt' => ''
        ];
        $flag = FeatureFlag::decode($flagJson);

        $result = $flag->evaluate(new LDUser('user'), static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testFlagReturnsErrorIfOffVariationIsNegative()
    {
        $flagJson = [
            'key' => 'feature',
            'version' => 1,
            'deleted' => false,
            'on' => false,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => -1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fall', 'off', 'on'],
            'salt' => ''
        ];
        $flag = FeatureFlag::decode($flagJson);

        $result = $flag->evaluate(new LDUser('user'), static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testFlagReturnsOffVariationIfPrerequisiteIsNotFound()
    {
        $flagJson = [
            'key' => 'feature0',
            'version' => 1,
            'deleted' => false,
            'on' => true,
            'targets' => [],
            'prerequisites' => [
                ['key' => 'feature1', 'variation' => 1]
            ],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fall', 'off', 'on'],
            'salt' => ''
        ];
        $flag = FeatureFlag::decode($flagJson);
        $ub = new LDUserBuilder('x');
        $user = $ub->build();
        $requester = new MockFeatureRequesterForFeature();

        $result = $flag->evaluate($user, $requester, static::$eventFactory);
        $detail = new EvaluationDetail('off', 1, EvaluationReason::prerequisiteFailed('feature1'));
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testFlagReturnsOffVariationAndEventIfPrerequisiteIsOff()
    {
        $flag0Json = [
            'key' => 'feature0',
            'version' => 1,
            'deleted' => false,
            'on' => true,
            'targets' => [],
            'prerequisites' => [
                ['key' => 'feature1', 'variation' => 1]
            ],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fall', 'off', 'on'],
            'salt' => ''
        ];
        $flag1Json = [
            'key' => 'feature1',
            'version' => 2,
            'deleted' => false,
            'on' => false,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 1,
            // note that even though it returns the desired variation, it is still off and therefore not a match
            'fallthrough' => ['variation' => 0],
            'variations' => ['nogo', 'go'],
            'salt' => ''
        ];
        $flag0 = FeatureFlag::decode($flag0Json);
        $flag1 = FeatureFlag::decode($flag1Json);
        $ub = new LDUserBuilder('x');
        $user = $ub->build();
        $requester = new MockFeatureRequesterForFeature();
        $requester->key = $flag1->getKey();
        $requester->val = $flag1;

        $result = $flag0->evaluate($user, $requester, static::$eventFactory);
        $detail = new EvaluationDetail('off', 1, EvaluationReason::prerequisiteFailed('feature1'));
        self::assertEquals($detail, $result->getDetail());

        $events = $result->getPrerequisiteEvents();
        self::assertEquals(1, count($events));
        $event = $events[0];
        self::assertEquals('feature', $event['kind']);
        self::assertEquals($flag1->getKey(), $event['key']);
        self::assertEquals('go', $event['value']);
        self::assertEquals($flag1->getVersion(), $event['version']);
        self::assertEquals($flag0->getKey(), $event['prereqOf']);
    }

    public function testFlagReturnsOffVariationAndEventIfPrerequisiteIsNotMet()
    {
        $flag0Json = [
            'key' => 'feature0',
            'version' => 1,
            'deleted' => false,
            'on' => true,
            'targets' => [],
            'prerequisites' => [
                ['key' => 'feature1', 'variation' => 1]
            ],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fall', 'off', 'on'],
            'salt' => ''
        ];
        $flag1Json = [
            'key' => 'feature1',
            'version' => 2,
            'deleted' => false,
            'on' => true,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['nogo', 'go'],
            'salt' => ''
        ];
        $flag0 = FeatureFlag::decode($flag0Json);
        $flag1 = FeatureFlag::decode($flag1Json);
        $ub = new LDUserBuilder('x');
        $user = $ub->build();
        $requester = new MockFeatureRequesterForFeature();
        $requester->key = $flag1->getKey();
        $requester->val = $flag1;

        $result = $flag0->evaluate($user, $requester, static::$eventFactory);
        $detail = new EvaluationDetail('off', 1, EvaluationReason::prerequisiteFailed('feature1'));
        self::assertEquals($detail, $result->getDetail());

        $events = $result->getPrerequisiteEvents();
        self::assertEquals(1, count($events));
        $event = $events[0];
        self::assertEquals('feature', $event['kind']);
        self::assertEquals($flag1->getKey(), $event['key']);
        self::assertEquals('nogo', $event['value']);
        self::assertEquals($flag1->getVersion(), $event['version']);
        self::assertEquals($flag0->getKey(), $event['prereqOf']);
    }

    public function testFlagReturnsFallthroughVariationAndEventIfPrerequisiteIsMetAndThereAreNoRules()
    {
        $flag0Json = [
            'key' => 'feature0',
            'version' => 1,
            'deleted' => false,
            'on' => true,
            'targets' => [],
            'prerequisites' => [
                ['key' => 'feature1', 'variation' => 1]
            ],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fall', 'off', 'on'],
            'salt' => ''
        ];
        $flag1Json = [
            'key' => 'feature1',
            'version' => 2,
            'deleted' => false,
            'on' => true,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 1],
            'variations' => ['nogo', 'go'],
            'salt' => ''
        ];
        $flag0 = FeatureFlag::decode($flag0Json);
        $flag1 = FeatureFlag::decode($flag1Json);
        $ub = new LDUserBuilder('x');
        $user = $ub->build();
        $requester = new MockFeatureRequesterForFeature();
        $requester->key = $flag1->getKey();
        $requester->val = $flag1;

        $result = $flag0->evaluate($user, $requester, static::$eventFactory);
        $detail = new EvaluationDetail('fall', 0, EvaluationReason::fallthrough());
        self::assertEquals($detail, $result->getDetail());

        $events = $result->getPrerequisiteEvents();
        self::assertEquals(1, count($events));
        $event = $events[0];
        self::assertEquals('feature', $event['kind']);
        self::assertEquals($flag1->getKey(), $event['key']);
        self::assertEquals('go', $event['value']);
        self::assertEquals($flag1->getVersion(), $event['version']);
        self::assertEquals($flag0->getKey(), $event['prereqOf']);
    }

    public function testFlagMatchesUserFromTargets()
    {
        $flagJson = [
            'key' => 'feature',
            'version' => 1,
            'deleted' => false,
            'on' => true,
            'targets' => [
                ['values' => ['whoever', 'userkey'], 'variation' => 2]
            ],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => ['fall', 'off', 'on'],
            'salt' => ''
        ];
        $flag = FeatureFlag::decode($flagJson);
        $ub = new LDUserBuilder('userkey');
        $user = $ub->build();

        $result = $flag->evaluate($user, static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail('on', 2, EvaluationReason::targetMatch());
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testFlagMatchesUserFromRules()
    {
        global $defaultUser;
        $flag = makeBooleanFlagWithRules([makeRuleMatchingUser($defaultUser, ['variation' => 1])]);

        $result = $flag->evaluate($defaultUser, static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail(true, 1, EvaluationReason::ruleMatch(0, RULE_ID));
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testFlagReturnsErrorIfRuleVariationIsTooHigh()
    {
        global $defaultUser;
        $flag = makeBooleanFlagWithRules([makeRuleMatchingUser($defaultUser, ['variation' => 999])]);

        $result = $flag->evaluate($defaultUser, static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testFlagReturnsErrorIfRuleVariationIsNegative()
    {
        global $defaultUser;
        $flag = makeBooleanFlagWithRules([makeRuleMatchingUser($defaultUser, ['variation' => -1])]);

        $result = $flag->evaluate($defaultUser, static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testFlagReturnsErrorIfRuleHasNoVariationOrRollout()
    {
        global $defaultUser;
        $flag = makeBooleanFlagWithRules([makeRuleMatchingUser($defaultUser, [])]);

        $result = $flag->evaluate($defaultUser, static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testFlagReturnsErrorIfRuleHasRolloutWithNoVariations()
    {
        global $defaultUser;
        $rollout = ['variations' => []];
        $flag = makeBooleanFlagWithRules([makeRuleMatchingUser($defaultUser, ['rollout' => $rollout])]);

        $result = $flag->evaluate($defaultUser, static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testRolloutSelectsBucket()
    {
        $ub = new LDUserBuilder('userkey');
        $user = $ub->build();
        $flagKey = 'flagkey';
        $salt = 'salt';
        
        // First verify that with our test inputs, the bucket value will be greater than zero and less than 100000,
        // so we can construct a rollout whose second bucket just barely contains that value
        $bucketValue = floor(VariationOrRollout::bucketUser($user, $flagKey, "key", $salt, null) * 100000);
        self::assertGreaterThan(0, $bucketValue);
        self::assertLessThan(100000, $bucketValue);

        $badVariationA = 0;
        $matchedVariation = 1;
        $badVariationB = 2;
        $rollout = [
            'variations' => [
                ['variation' => $badVariationA, 'weight' => $bucketValue], // end of bucket range is not inclusive, so it will *not* match the target value
                ['variation' => $matchedVariation, 'weight' => 1], // size of this bucket is 1, so it only matches that specific value
                ['variation' => $badVariationB, 'weight' => 100000 - ($bucketValue + 1)]
            ]
        ];
        $flag = FeatureFlag::decode([
            'key' => $flagKey,
            'version' => 1,
            'deleted' => false,
            'on' => true,
            'offVariation' => null,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'fallthrough' => ['rollout' => $rollout],
            'variations' => ['', '', ''],
            'salt' => $salt
        ]);

        $result = $flag->evaluate($user, static::$requester, static::$eventFactory);
        self::assertSame($matchedVariation, $result->getDetail()->getVariationIndex());
    }

    public function testRolloutSelectsLastBucketIfBucketValueEqualsTotalWeight()
    {
        $ub = new LDUserBuilder('userkey');
        $user = $ub->build();
        $flagKey = 'flagkey';
        $salt = 'salt';
        
        $bucketValue = floor(VariationOrRollout::bucketUser($user, $flagKey, "key", $salt, null) * 100000);

        // We'll construct a list of variations that stops right at the target bucket value
        $rollout = [
            'variations' => [
                ['variation' => 0, 'weight' => $bucketValue]
            ]
        ];
        $flag = FeatureFlag::decode([
            'key' => $flagKey,
            'version' => 1,
            'deleted' => false,
            'on' => true,
            'offVariation' => null,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'fallthrough' => ['rollout' => $rollout],
            'variations' => [''],
            'salt' => $salt
        ]);

        $result = $flag->evaluate($user, static::$requester, static::$eventFactory);
        self::assertSame(0, $result->getDetail()->getVariationIndex());
    }

    public function testRolloutCalculationBucketsByUserKeyByDefault()
    {
        $ub = new LDUserBuilder('userkey');
        $user = $ub->build();
        $expectedBucketValue = 22464;
        $rollout = [
            'salt' => '',
            'variations' => makeRolloutVariations($expectedBucketValue, 1, 0)
        ];
        $flag = makeBooleanFlagWithRules([makeRuleMatchingUser($user, ['rollout' => $rollout])]);

        $result = $flag->evaluate($user, static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail(true, 1, EvaluationReason::ruleMatch(0, RULE_ID));
        self::assertEquals($detail, $result->getDetail());
    }

    public function testRolloutCalculationCanBucketBySpecificAttribute()
    {
        $ub = new LDUserBuilder('userkey');
        $ub->name('Bob');
        $user = $ub->build();
        $expectedBucketValue = 95913;
        $rollout = [
            'salt' => '',
            'bucketBy' => 'name',
            'variations' => makeRolloutVariations($expectedBucketValue, 1, 0)
        ];
        $flag = makeBooleanFlagWithRules([makeRuleMatchingUser($user, ['rollout' => $rollout])]);

        $result = $flag->evaluate($user, static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail(true, 1, EvaluationReason::ruleMatch(0, RULE_ID));
        self::assertEquals($detail, $result->getDetail());
    }

    public function testRolloutCalculationIncludesSecondaryKey()
    {
        $ub = new LDUserBuilder('userkey');
        $ub->secondary('999');
        $user = $ub->build();
        $expectedBucketValue = 31179;
        $rollout = [
            'salt' => '',
            'variations' => makeRolloutVariations($expectedBucketValue, 1, 0)
        ];
        $flag = makeBooleanFlagWithRules([makeRuleMatchingUser($user, ['rollout' => $rollout])]);

        $result = $flag->evaluate($user, static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail(true, 1, EvaluationReason::ruleMatch(0, RULE_ID));
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testRolloutCalculationCoercesSecondaryKeyToString()
    {
        // This should produce the same result as the previous test, and should not cause an error (ch35189).
        $ub = new LDUserBuilder('userkey');
        $ub->secondary(999);
        $user = $ub->build();
        $expectedBucketValue = 31179;
        $rollout = [
            'salt' => '',
            'variations' => makeRolloutVariations($expectedBucketValue, 1, 0)
        ];
        $flag = makeBooleanFlagWithRules([makeRuleMatchingUser($user, ['rollout' => $rollout])]);

        $result = $flag->evaluate($user, static::$requester, static::$eventFactory);
        $detail = new EvaluationDetail(true, 1, EvaluationReason::ruleMatch(0, RULE_ID));
        self::assertEquals($detail, $result->getDetail());
        self::assertEquals([], $result->getPrerequisiteEvents());
    }

    public function testClauseCanMatchBuiltInAttribute()
    {
        $clause = ['attribute' => 'name', 'op' => 'in', 'values' => ['Bob'], 'negate' => false];
        $flag = makeBooleanFlagWithClauses([$clause]);
        $ub = new LDUserBuilder('userkey');
        $ub->name('Bob');
        $user = $ub->build();

        $result = $flag->evaluate($user, static::$requester, static::$eventFactory);
        self::assertEquals(true, $result->getDetail()->getValue());
    }

    public function testClauseCanMatchCustomAttribute()
    {
        $clause = ['attribute' => 'legs', 'op' => 'in', 'values' => ['4'], 'negate' => false];
        $flag = makeBooleanFlagWithClauses([$clause]);
        $ub = new LDUserBuilder('userkey');
        $ub->customAttribute('legs', 4);
        $user = $ub->build();

        $result = $flag->evaluate($user, static::$requester, static::$eventFactory);
        self::assertEquals(true, $result->getDetail()->getValue());
    }

    public function testClauseReturnsFalseForMissingAttribute()
    {
        $clause = ['attribute' => 'legs', 'op' => 'in', 'values' => ['4'], 'negate' => false];
        $flag = makeBooleanFlagWithClauses([$clause]);
        $ub = new LDUserBuilder('userkey');
        $user = $ub->build();

        $result = $flag->evaluate($user, static::$requester, static::$eventFactory);
        self::assertEquals(false, $result->getDetail()->getValue());
    }

    public function testClauseCanBeNegated()
    {
        $clause = ['attribute' => 'name', 'op' => 'in', 'values' => ['Bob'], 'negate' => true];
        $flag = makeBooleanFlagWithClauses([$clause]);
        $ub = new LDUserBuilder('userkey');
        $ub->name('Bob');
        $user = $ub->build();

        $result = $flag->evaluate($user, static::$requester, static::$eventFactory);
        self::assertEquals(false, $result->getDetail()->getValue());
    }

    public function testClauseWithUnknownOperatorDoesNotMatch()
    {
        $clause = ['attribute' => 'name', 'op' => 'doesSomethingUnsupported', 'values' => ['Bob'], 'negate' => false];
        $flag = makeBooleanFlagWithClauses([$clause]);
        $ub = new LDUserBuilder('userkey');
        $ub->name('Bob');
        $user = $ub->build();

        $result = $flag->evaluate($user, static::$requester, static::$eventFactory);
        self::assertEquals(false, $result->getDetail()->getValue());
    }

    public function testSegmentMatchClauseRetrievesSegmentFromStore()
    {
        global $defaultUser;
        $segmentJson = [
            'key' => 'segkey',
            'version' => 1,
            'deleted' => false,
            'included' => [$defaultUser->getKey()],
            'excluded' => [],
            'rules' => [],
            'salt' => ''
        ];
        $segment = Segment::decode($segmentJson);

        $requester = new MockFeatureRequesterForSegment();
        $requester->key = 'segkey';
        $requester->val = $segment;

        $feature = makeBooleanFlagWithClauses([makeSegmentMatchClause('segkey')]);

        $result = $feature->evaluate($defaultUser, $requester, static::$eventFactory);

        self::assertTrue($result->getDetail()->getValue());
    }

    public function testSegmentMatchClauseFallsThroughWithNoErrorsIfSegmentNotFound()
    {
        global $defaultUser;
        $requester = new MockFeatureRequesterForSegment();
        
        $feature = makeBooleanFlagWithClauses([makeSegmentMatchClause('segkey')]);

        $result = $feature->evaluate($defaultUser, $requester, static::$eventFactory);

        self::assertFalse($result->getDetail()->getValue());
    }
}
