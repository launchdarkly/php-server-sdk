<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\FeatureFlag;
use LaunchDarkly\FeatureRequester;
use LaunchDarkly\LDUserBuilder;
use LaunchDarkly\Segment;


class FeatureFlagTest extends \PHPUnit_Framework_TestCase
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
        FeatureFlag::decode(\GuzzleHttp\json_decode(FeatureFlagTest::$json1, true));
        FeatureFlag::decode(\GuzzleHttp\json_decode(FeatureFlagTest::$json2, true));
    }
    
    public function dataDecodeMulti()
    {
        return [
            'null-prerequisites' => [
                [
                    'key' => 'sysops-test',
                    'version' => 14,
                    'on' => true,
                    'prerequisites' => NULL,
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
                    'offVariation' => NULL,
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

    public function testSegmentMatchClauseRetrievesSegmentFromStore()
    {
        $segmentJson = array(
            'key' => 'segkey',
            'version' => 1,
            'deleted' => false,
            'included' => array('foo'),
            'excluded' => array(),
            'rules' => array(),
            'salt' => ''
        );
        $segment = Segment::decode($segmentJson);

        $requester = new MockFeatureRequesterForSegment();
        $requester->key = 'segkey';
        $requester->val = $segment;

        $feature = $this->makeBooleanFeatureWithSegmentMatch('segkey');

        $ub = new LDUserBuilder('foo');
        $user = $ub->build();

        $result = $feature->evaluate($user, $requester);

        self::assertTrue($result->getValue());
    }

    public function testSegmentMatchClauseFallsThroughWithNoErrorsIfSegmentNotFound()
    {
        $requester = new MockFeatureRequesterForSegment();
        
        $feature = $this->makeBooleanFeatureWithSegmentMatch('segkey');

        $ub = new LDUserBuilder('foo');
        $user = $ub->build();

        $result = $feature->evaluate($user, $requester);

        self::assertFalse($result->getValue());
    }

    private function makeBooleanFeatureWithSegmentMatch($segmentKey)
    {
        $featureJson = array(
            'key' => 'test',
            'version' => 1,
            'deleted' => false,
            'on' => true,
            'variations' => array(false, true),
            'fallthrough' => array('variation' => 0),
            'rules' => array(
                array(
                    'clauses' => array(
                        array(
                            'attribute' => '',
                            'op' => 'segmentMatch',
                            'values' => array($segmentKey),
                            'negate' => false
                        )
                    ),
                    'variation' => 1
                )
            ),
            'offVariation' => 0,
            'prerequisites' => array(),
            'targets' => array(),
            'salt' => ''
        );
        return FeatureFlag::decode($featureJson);
    }
}


class MockFeatureRequesterForSegment implements FeatureRequester
{
    public $key = null;
    public $val = null;

    function __construct($baseurl = null, $key = null, $options = null)
    {
    }

    public function getFeature($key)
    {
        return null;
    }

    public function getSegment($key)
    {
        return ($key == $this->key) ? $this->val : null;
    }

    public function getAllFeatures()
    {
        return null;
    }
}

