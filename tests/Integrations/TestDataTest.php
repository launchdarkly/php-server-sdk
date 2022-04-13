<?php

namespace LaunchDarkly\Tests\Integrations;

use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Integrations\TestData;
use LaunchDarkly\LDClient;
use LaunchDarkly\LDUserBuilder;
use LaunchDarkly\Tests;
use PHPUnit\Framework\TestCase;

class TestDataTest extends TestCase
{
    public function testMakesFlag()
    {
        $td = new TestData();
        $flag = $td->flag('test-flag');

        $this->assertEquals('test-flag', $flag->build(0)['key']);
        $this->assertEquals(true, $flag->build(0)['on']);
    }

    public function testMakesCopy()
    {
        $td = new TestData();
        $flag = $td->flag('test-flag');

        $flag->variations(1, 2, 3);

        $this->assertEquals([1,2,3], $flag->build(0)['variations']);

        $flagCopy = $flag->copy();

        $flagCopy->variations(4, 5, 6);

        $this->assertEquals([1,2,3], $flag->build(0)['variations']);
        $this->assertEquals([4,5,6], $flagCopy->build(0)['variations']);
    }

    public function testCanReferenceSameFlag()
    {
        $td = new TestData();
        $td->update($td->flag('test-flag')->variations('red', 'blue'));

        $flag = $td->flag('test-flag');
        $this->assertEquals(['red','blue'], $flag->build(0)['variations']);
    }

    public function provideFlagConfig()
    {
        $td = new TestData();
        return [
            'flag has fallthrough variation by default' => [
                [
                    'on' => true,
                    'fallthrough' => ['variation' => 0]
                ],
                $td->flag('test-flag-1')->build(0)
            ],
            'explicitly changing empty flag to boolean flag has no effect compared to default flag' => [
                [
                    'on' => true,
                    'fallthrough' => ['variation' => 0]
                ],
                $td->flag('test-flag-2')->booleanFlag()->build(0)
            ],
            'explicitly changing empty flag to be on has no effect compared to default flag' => [
                [
                    'on' => true,
                    'fallthrough' => ['variation' => 0]
                ],
                $td->flag('test-flag-3')->on(true)->build(0)
            ],
            'flag can be turned off' => [
                [
                    'on' => false,
                    'fallthrough' => ['variation' => 0]
                ],
                $td->flag('test-flag-4')->on(false)->build(0)
            ],
            'flag can set false boolean variation for all users' => [
                [
                    'on' => true,
                    'offVariation' => 1,
                    'variations' => [true, false],
                    'fallthrough' => ['variation' => 1],
                ],
                $td->flag('test-flag-5')->variationForAllUsers(false)->build(0)
            ],
            'flag can set true boolean variation for all users' => [
                [
                    'on' => true,
                    'offVariation' => 1,
                    'variations' => [true, false],
                    'fallthrough' => ['variation' => 0],
                ],
                $td->flag('test-flag-6')->variationForAllUsers(true)->build(0)
            ],
        ];
    }

    /**
     * @dataProvider provideFlagConfig
     */
    public function testFlagConfigSimpleBoolean($expected, $actual)
    {
        foreach (array_keys($expected) as $key) {
            $this->assertEquals($expected[$key], $actual[$key]);
        }
    }


    public function testFlagBuilderBooleanConfigMethodsForcesFlagToBeBoolean()
    {
        $td = new TestData();
        $overwriteBoolFlag1 = $td->flag('test-flag')->variations(1, 2)->booleanFlag()->build(0);
        $this->assertEquals([true, false], $overwriteBoolFlag1['variations']);
        $this->assertEquals(true, $overwriteBoolFlag1['on']);
        $this->assertEquals(1, $overwriteBoolFlag1['offVariation']);
        $this->assertEquals(['variation' => 0], $overwriteBoolFlag1['fallthrough']);

        $overwriteBoolFlag2 = $td->flag('test-flag')->variations(true, 2)->booleanFlag()->build(0);
        $this->assertEquals([true, false], $overwriteBoolFlag2['variations']);
        $this->assertEquals(true, $overwriteBoolFlag2['on']);
        $this->assertEquals(1, $overwriteBoolFlag2['offVariation']);
        $this->assertEquals(['variation' => 0], $overwriteBoolFlag2['fallthrough']);

        $boolFlag = $td->flag('test-flag')->booleanFlag()->build(0);
        $this->assertEquals([true, false], $boolFlag['variations']);
        $this->assertEquals(true, $boolFlag['on']);
        $this->assertEquals(1, $boolFlag['offVariation']);
        $this->assertEquals(['variation' => 0], $boolFlag['fallthrough']);
    }

    public function testFlagConfigStringVariations()
    {
        $td = new TestData();
        $stringVariationFlag = $td->flag('test-flag')
                                    ->variations('red', 'green', 'blue')
                                    ->offVariation(0)
                                    ->fallthroughVariation(2)
                                    ->build(0);
        $this->assertEquals(['red', 'green', 'blue'], $stringVariationFlag['variations']);
        $this->assertEquals(true, $stringVariationFlag['on']);
        $this->assertEquals(0, $stringVariationFlag['offVariation']);
        $this->assertEquals(['variation' => 2], $stringVariationFlag['fallthrough']);
    }

    public function testUserTargetsCanSetSameVariationForDistinctUsers()
    {
        $td = new TestData();
        $flagBool1 = $td->flag('test-flag-1')
                        ->variationForUser("a", true)
                        ->variationForUser("b", true)
                        ->build(0);
        $this->assertEquals(true, $flagBool1['on']);
        $this->assertEquals([true, false], $flagBool1['variations']);
        $this->assertEquals(1, $flagBool1['offVariation']);
        $this->assertEquals(['variation' => 0], $flagBool1['fallthrough']);
        $expectedTargets = [
            ['variation' => 0, 'values' => ["a", "b"]],
        ];
        $this->assertEquals($expectedTargets, $flagBool1['targets']);
    }

    public function testUserTargetsWillNotDuplicateSameUser()
    {
        $td = new TestData();

        $flagBool2 = $td->flag('test-flag-2')
                        ->variationForUser("a", true)
                        ->variationForUser("a", true)
                        ->build(0);
        $this->assertEquals(true, $flagBool2['on']);
        $this->assertEquals([true, false], $flagBool2['variations']);
        $this->assertEquals(1, $flagBool2['offVariation']);
        $this->assertEquals(['variation' => 0], $flagBool2['fallthrough']);
        $expectedTargets = [
            ['variation' => 0, 'values' => ["a"]],
        ];
        $this->assertEquals($expectedTargets, $flagBool2['targets']);
    }

    public function testUserTargetsCanSetDistinctVariationsForDistinctUsers()
    {
        $td = new TestData();

        $flagBool3 = $td->flag('test-flag-3')
                        ->variationForUser("a", false)
                        ->variationForUser("b", true)
                        ->variationForUser("c", false)
                        ->build(0);
        $this->assertEquals(true, $flagBool3['on']);
        $this->assertEquals([true, false], $flagBool3['variations']);
        $this->assertEquals(1, $flagBool3['offVariation']);
        $this->assertEquals(['variation' => 0], $flagBool3['fallthrough']);
        $expectedTargets = [
            ['variation' => 0, 'values' => ["b"]],
            ['variation' => 1, 'values' => ["a", "c"]],
        ];
        $this->assertEquals($expectedTargets, $flagBool3['targets']);
    }

    public function testUserTargetsCanModifyVariationForSpecificUser()
    {
        $td = new TestData();
        $flagBool4 = $td->flag('test-flag-3')
                        ->variationForUser("a", true)
                        ->variationForUser("b", true)
                        ->variationForUser("a", false)
                        ->build(0);
        $this->assertEquals(true, $flagBool4['on']);
        $this->assertEquals([true, false], $flagBool4['variations']);
        $this->assertEquals(1, $flagBool4['offVariation']);
        $this->assertEquals(['variation' => 0], $flagBool4['fallthrough']);
        $expectedTargets = [
            ['variation' => 0, 'values' => ["b"]],
            ['variation' => 1, 'values' => ["a"]],
        ];
        $this->assertEquals($expectedTargets, $flagBool4['targets']);
    }

    public function testUserTargetsCanSetVariationsWithFallbackValues()
    {
        $td = new TestData();
        $flagString1 = $td->flag('test-flag-4')
                        ->variations('red', 'green', 'blue')
                        ->offVariation(0)
                        ->fallthroughVariation(2)
                        ->variationForUser("a", 2)
                        ->variationForUser("b", 2)
                        ->build(0);
        $this->assertEquals(['red', 'green', 'blue'], $flagString1['variations']);
        $this->assertEquals(true, $flagString1['on']);
        $this->assertEquals(0, $flagString1['offVariation']);
        $this->assertEquals(['variation' => 2], $flagString1['fallthrough']);
        $expectedTargets = [
            ['variation' => 2, 'values' => ["a", "b"]],
        ];
        $this->assertEquals($expectedTargets, $flagString1['targets']);
    }

    public function testUserTargetsCanSetVariationsWithFallbackValuesWithDistinctUserVariations()
    {
        $td = new TestData();

        $flagString2 = $td->flag('test-flag-5')
                        ->variations('red', 'green', 'blue')
                        ->offVariation(0)
                        ->fallthroughVariation(2)
                        ->variationForUser("a", 2)
                        ->variationForUser("b", 1)
                        ->variationForUser("c", 2)
                        ->build(0);
        $this->assertEquals(['red', 'green', 'blue'], $flagString2['variations']);
        $this->assertEquals(true, $flagString2['on']);
        $this->assertEquals(0, $flagString2['offVariation']);
        $this->assertEquals(['variation' => 2], $flagString2['fallthrough']);
        $expectedTargets = [
            ['variation' => 1, 'values' => ["b"]],
            ['variation' => 2, 'values' => ["a", "c"]],
        ];
        $this->assertEquals($expectedTargets, $flagString2['targets']);
    }

    public function testUserTargetsWillIgnoreSettingNonexistentUserVariation()
    {
        $td = new TestData();

        $flagString2 = $td->flag('test-flag-5')
                        ->variations('red', 'green', 'blue')
                        ->variationForUser("a", 1)
                        ->variationForUser("b", 4)
                        ->build(0);
        $this->assertEquals(['red', 'green', 'blue'], $flagString2['variations']);
        $this->assertEquals(true, $flagString2['on']);
        $expectedTargets = [
            ['variation' => 1, 'values' => ["a"]],
        ];
        $this->assertEquals($expectedTargets, $flagString2['targets']);
    }

    public function testFlagbuilderCanSetValueForAllUsers()
    {
        $jsonString1 = "
            {
                \"boolField\": true,
                \"stringField\": \"some val\",
                \"intField\": 1,
                \"arrayField\": [\"cat\", \"dog\", \"fish\" ],
                \"objectField\": {\"animal\": \"dog\" }
            }
        ";
        $testObject = [
            "boolField" => true,
            "stringField" => "some val",
            "intField" => 1,
            "arrayField" => ["cat", "dog", "fish" ],
            "objectField" => [ "animal" => "dog" ]
        ];

        $td = new TestData();
        $flagFromJSONString = $td->flag('test-flag');
        $testObjFromStr = json_decode($jsonString1);
        $flagFromJSONString->valueForAllUsers($testObjFromStr);
        $this->assertEquals([$testObject], $flagFromJSONString->build(0)['variations']);

        $flagBoolean = $td->flag('test-flag');
        $flagBoolean->valueForAllUsers(false);
        $this->assertEquals([false], $flagBoolean->build(0)['variations']);

        $flagInt = $td->flag('test-flag');
        $flagInt->valueForAllUsers(4);
        $this->assertEquals([4], $flagInt->build(0)['variations']);

        $flagArray = $td->flag('test-flag');
        $flagArray->valueForAllUsers(['cat', 'dog', 'fish']);
        $this->assertEquals([ ['cat', 'dog', 'fish'] ], $flagArray->build(0)['variations']);

        $flagAssociatedArray = $td->flag('test-flag');
        $flagAssociatedArray->valueForAllUsers(['animal' => 'dog', 'legs' => 4]);
        $this->assertEquals([ ['animal' => 'dog', 'legs' => 4] ], $flagAssociatedArray->build(0)['variations']);

        $flagNull = $td->flag('test-flag');
        $flagNull->valueForAllUsers(null);
        $this->assertEquals([null], $flagNull->build(0)['variations']);

        $flagObject = $td->flag('test-flag');
        $flagObject->valueForAllUsers((object) ['animal' => 'dog', 'legs' => 4]);
        $this->assertEquals([['animal' => 'dog', 'legs' => 4]], $flagObject->build(0)['variations']);
    }

    public function testSetsVariations()
    {
        $td = new TestData();
        $flag = $td->flag('new-flag')->variations('red', 'green', 'blue');
        $this->assertEquals(['red', 'green', 'blue'], $flag->build(0)['variations']);

        $flag2 = $td->flag('new-flag-2')->variations(['red', 'green', 'blue']);
        $this->assertEquals([['red', 'green', 'blue']], $flag2->build(0)['variations']);

        $flag3 = $td->flag('new-flag-3')->variations(['red', 'green', 'blue'], ['cat', 'dog', 'fish']);
        $this->assertEquals([['red', 'green', 'blue'], ['cat', 'dog', 'fish']], $flag3->build(0)['variations']);

        $flag4 = $td->flag('new-flag-4')->variations([['red', 'green', 'blue']]);
        $this->assertEquals([['red', 'green', 'blue']], $flag4->build(0)['variations'][0]);
    }

    public function testFlagBuilderCanSetFallthroughVariation()
    {
        $td = new TestData();
        $flag = $td->flag('test-flag');
        $flag->fallthroughVariation(2);

        $this->assertEquals(['variation' => 2], $flag->build(0)['fallthrough']);
    }

    public function testFlagBuilderClearUserTargets()
    {
        $td = new TestData();
        $flag = $td->flag('test-flag')
                   ->variationForUser('user-1', 0)
                   ->variationForUser('user-2', 1)
                   ->clearUserTargets()
                   ->build(0);
        $this->assertEquals([], $flag['targets']);
    }


    public function testFlagBuilderDefaultsToBooleanFlag()
    {
        $td = new TestData();
        $flag = $td->flag('empty-flag');
        $this->assertEquals([true, false], $flag->build(0)['variations']);
        $this->assertEquals(['variation' => 0], $flag->build(0)['fallthrough']);
        $this->assertEquals(1, $flag->build(0)['offVariation']);
    }

    public function testFlagbuilderCanTurnFlagOff()
    {
        $td = new TestData();
        $flag = $td->flag('test-flag');
        $flag->on(false);

        $this->assertEquals(false, $flag->build(0)['on']);
    }

    public function testFlagbuilderCanSetVariationWhenTargetingIsOff()
    {
        $td = new TestData();
        $flag = $td->flag('test-flag')->on(false);
        $this->assertEquals(false, $flag->build(0)['on']);
        $this->assertEquals([true,false], $flag->build(0)['variations']);
        $flag->variations('dog', 'cat');
        $this->assertEquals(['dog','cat'], $flag->build(0)['variations']);
    }

    public function testFlagbuilderCanSetVariationForAllUsers()
    {
        $td = new TestData();
        $flag = $td->flag('test-flag')->variationForAllUsers(true)->build(0);
        $this->assertEquals(['variation' => 0], $flag['fallthrough']);
    }


    public function testCanSetAndGetFeatureFlag()
    {
        $key = 'test-flag';
        $expectedFlagJson = [
            'key' => $key,
            'version' => 1,
            'deleted' => false,
            'on' => true,
            'targets' => [],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 0],
            'variations' => [true, false],

            /* Required FeatureFlag fields */
            'salt' => null,
            'prerequisites' => [],
        ];
        $expectedFeatureFlag = FeatureFlag::decode($expectedFlagJson);

        $td = new TestData();
        $flag = $td->flag($key);
        $td->update($flag);
        $featureFlag = $td->getFeature($key);
        $this->assertEquals($expectedFeatureFlag, $featureFlag);
    }

    public function testFlagBuilderCanAddAndBuildRules()
    {
        $td = new TestData();
        $flag = $td->flag("flag")
                   ->ifMatch("name", "Patsy", "Edina")
                   ->andNotMatch("country", "gb")
                   ->thenReturn(true);
        $builtFlag = $flag->build(0);
        $expectedRule = [
            [
                "id" => "rule0",
                "variation" => 0,
                "clauses" => [
                    [
                        "attribute" => "name",
                        "op" => 'in',
                        "values" => ["Patsy", "Edina"],
                        "negate" => false,
                    ],
                    [
                        "attribute" => "country",
                        "op" => 'in',
                        "values" => ["gb"],
                        "negate" => true,
                    ]
                ]
            ]
        ];
        $this->assertEquals($expectedRule, $builtFlag['rules']);
    }

    public function testUsingTestDataInClientEvaluations()
    {
        $td = new TestData();
        $flagBuilder = $td->flag("flag")
                          ->fallthroughVariation(false)
                          ->ifMatch("firstName", "Patsy", "Edina")
                          ->andNotMatch("lastName", "Cline", "Gallovits-Hall")
                          ->thenReturn(true);

        $td->update($flagBuilder);

        $options = [
            'feature_requester' => $td,
            'event_processor' => new Tests\MockEventProcessor()
        ];
        $client = new LDClient("someKey", $options);

        $userBuilder = new LDUserBuilder("someKey");

        $userBuilder->firstName("Janet")->lastName("Cline");
        $this->assertFalse($client->variation("flag", $userBuilder->build()));

        $userBuilder->firstName("Patsy")->lastName("Cline");
        $this->assertFalse($client->variation("flag", $userBuilder->build()));

        $userBuilder->firstName("Patsy")->lastName("Smith");
        $this->assertTrue($client->variation("flag", $userBuilder->build()));
    }
}
