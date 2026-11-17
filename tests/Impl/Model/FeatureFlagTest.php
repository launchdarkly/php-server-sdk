<?php

namespace LaunchDarkly\Tests\Impl\Model;

use LaunchDarkly\Impl\Model\FeatureFlag;

use PHPUnit\Framework\TestCase;

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

    public function testDecode()
    {
        $this->assertInstanceOf(FeatureFlag::class, FeatureFlag::decode(json_decode(FeatureFlagTest::$json1, true)));
        $this->assertInstanceOf(FeatureFlag::class, FeatureFlag::decode(json_decode(FeatureFlagTest::$json2, true)));
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

    public function testDecodeFlagWithoutOptionalProperties()
    {
        $json = '{"key": "flag", "version": 1, "on": true, "fallthrough": {"variation": 1},
            "variations": ["a", "b"],
            "targets": [{"variation": 1, "values": ["target-key"]}],
            "rules": [{"variation": 1, "clauses": [{"attribute": "key", "op": "in", "values": ["rule-key"]}]}]}';

        // Decode a flag that omits every optional property.
        $flag = FeatureFlag::decode(json_decode($json, true));

        // Each omitted property takes its default value.
        self::assertSame('', $flag->getSalt());
        self::assertSame([], $flag->getPrerequisites());
        self::assertSame([], $flag->getContextTargets());
        self::assertNull($flag->getOffVariation());
        self::assertFalse($flag->isDeleted());
        self::assertFalse($flag->isTrackEvents());
        self::assertFalse($flag->isTrackEventsFallthrough());
        self::assertFalse($flag->isClientSide());
        self::assertNull($flag->getTargets()[0]->getContextKind());
        self::assertNull($flag->getRules()[0]->getId());
        self::assertFalse($flag->getRules()[0]->isTrackEvents());
        self::assertFalse($flag->getRules()[0]->getClauses()[0]->isNegate());
    }

    public function testDecodeFlagWithNullOptionalProperties()
    {
        $json = '{"key": "flag", "version": 1, "on": true, "salt": null, "deleted": null,
            "fallthrough": {"variation": 1, "rollout": null}, "variations": ["a", "b"],
            "offVariation": null, "prerequisites": null, "targets": null, "contextTargets": null,
            "trackEvents": null, "trackEventsFallthrough": null, "clientSide": null,
            "debugEventsUntilDate": null, "samplingRatio": null, "excludeFromSummaries": null,
            "migration": null,
            "rules": [{"id": null, "variation": 1, "rollout": null, "trackEvents": null,
                "clauses": [{"contextKind": null, "attribute": "key", "op": "in", "values": ["rule-key"],
                    "negate": null}]}]}';

        // Decode a flag whose optional properties are all null.
        $flag = FeatureFlag::decode(json_decode($json, true));

        // Each null property takes its default value.
        self::assertSame('', $flag->getSalt());
        self::assertFalse($flag->isDeleted());
        self::assertSame([], $flag->getPrerequisites());
        self::assertSame([], $flag->getTargets());
        self::assertSame([], $flag->getContextTargets());
        self::assertNull($flag->getOffVariation());
        self::assertNull($flag->getFallthrough()->getRollout());
        self::assertFalse($flag->isTrackEvents());
        self::assertFalse($flag->isClientSide());
        self::assertNull($flag->getDebugEventsUntilDate());
        self::assertNull($flag->getMigrationSettings());
        self::assertNull($flag->getRules()[0]->getRollout());
        self::assertFalse($flag->getRules()[0]->getClauses()[0]->isNegate());
        self::assertNull($flag->getRules()[0]->getClauses()[0]->getContextKind());
    }
}
