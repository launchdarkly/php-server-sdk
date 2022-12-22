<?php

namespace LaunchDarkly\Tests\Integrations;

use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Integrations\TestData;
use LaunchDarkly\LDClient;
use LaunchDarkly\LDContext;
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

    public function defaultFlagProps()
    {
        return [
            "key" => "flagkey",
            "version" => 1,
            "on" => true,
            "prerequisites" => [],
            "targets" => [],
            "contextTargets" => [],
            "rules" => [],
            "salt" => "",
            "variations" => [true, false],
            "offVariation" => 1,
            "fallthrough" => ["variation" => 0],
            "deleted" => false
        ];
    }

    public function flagConfigParameterizedTestParams()
    {
        return [
            'defaults' => [
                [],
                fn ($f) => $f
            ],
            'changing default flag to boolean flag has no effect' => [
                [],
                fn ($f) => $f->booleanFlag()
            ],
            'non-boolean flag can be changed to boolean flag' => [
                [],
                fn ($f) => $f->variations('a', 'b')->booleanFlag()
            ],
            'flag can be turned off' => [
                [
                    'on' => false
                ],
                fn ($f) => $f->on(false)
            ],
            'flag can be turned on' => [
                [],
                fn ($f) => $f->on(false)->on(true)
            ],
            'set false boolean variation for all' => [
                [
                    'fallthrough' => ['variation' => 1],
                ],
                fn ($f) => $f->variationForAll(false)
            ],
            'set true boolean variation for all' => [
                [
                    'variations' => [true, false],
                    'fallthrough' => ['variation' => 0],
                ],
                fn ($f) => $f->variationForAll(true)
            ],
            'set false boolean variation for all users' => [
                [
                    'fallthrough' => ['variation' => 1],
                ],
                fn ($f) => $f->variationForAllUsers(false)
            ],
            'set true boolean variation for all users' => [
                [
                    'variations' => [true, false],
                    'fallthrough' => ['variation' => 0],
                ],
                fn ($f) => $f->variationForAllUsers(true)
            ],
            'set variation index for all' => [
                [
                    'fallthrough' => ['variation' => 2],
                    'variations' => ['a', 'b', 'c']
                ],
                fn ($f) => $f->variations('a', 'b', 'c')->variationForAll(2)
            ],
            'set variation index for all users' => [
                [
                    'fallthrough' => ['variation' => 2],
                    'variations' => ['a', 'b', 'c']
                ],
                fn ($f) => $f->variations('a', 'b', 'c')->variationForAllUsers(2)
            ],
            'set fallthrough variation boolean' => [
                [
                    'fallthrough' => ['variation' => 1]
                ],
                fn ($f) => $f->fallthroughVariation(false)
            ],
            'set fallthrough variation index' => [
                [
                    'variations' => ['a', 'b', 'c'],
                    'fallthrough' => ['variation' => 2]
                ],
                fn ($f) => $f->variations('a', 'b', 'c')->fallthroughVariation(2)
            ],
            'set off variation boolean' => [
                [
                    'offVariation' => 0
                ],
                fn ($f) => $f->offVariation(true)
            ],
            'set off variation index' => [
                [
                    'variations' => ['a', 'b', 'c'],
                    'offVariation' => 2
                ],
                fn ($f) => $f->variations('a', 'b', 'c')->offVariation(2)
            ],
            'set context targets as boolean' => [
                [
                    'targets' => [
                        ['variation' => 0, 'values' => ['key1', 'key2']],
                    ],
                    'contextTargets' => [
                        ['contextKind' => 'user', 'variation' => 0, 'values' => []],
                        ['contextKind' => 'kind1', 'variation' => 0, 'values' => ['key3', 'key4']],
                        ['contextKind' => 'kind1', 'variation' => 1, 'values' => ['key5', 'key6']],
                    ]
                ],
                fn ($f) => $f->variationForKey('user', 'key1', true)
                    ->variationForKey('user', 'key2', true)
                    ->variationForKey('kind1', 'key3', true)
                    ->variationForKey('kind1', 'key5', false)
                    ->variationForKey('kind1', 'key4', true)
                    ->variationForKey('kind1', 'key6', false)
            ],
            'set context targets as variation index' => [
                [
                    'variations' => ['a', 'b'],
                    'targets' => [
                        ['variation' => 0, 'values' => ['key1', 'key2']],
                    ],
                    'contextTargets' => [
                        ['contextKind' => 'user', 'variation' => 0, 'values' => []],
                        ['contextKind' => 'kind1', 'variation' => 0, 'values' => ['key3', 'key4']],
                        ['contextKind' => 'kind1', 'variation' => 1, 'values' => ['key5', 'key6']],
                    ]
                ],
                fn ($f) => $f->variations('a', 'b')
                    ->variationForKey('user', 'key1', 0)
                    ->variationForKey('user', 'key2', 0)
                    ->variationForKey('kind1', 'key3', 0)
                    ->variationForKey('kind1', 'key5', 1)
                    ->variationForKey('kind1', 'key4', 0)
                    ->variationForKey('kind1', 'key6', 1)
            ],
            'replace existing context target key' => [
                [
                    'contextTargets' => [
                        ['contextKind' => 'kind1', 'variation' => 0, 'values' => ['key1', 'key2']],
                        ['contextKind' => 'kind1', 'variation' => 1, 'values' => ['key3']]
                    ]
                ],
                fn ($f) => $f->variationForKey('kind1', 'key1', 0)
                    ->variationForKey('kind1', 'key2', 1)
                    ->variationForKey('kind1', 'key3', 1)
                    ->variationForKey('kind1', 'key2', 0)
            ],
            'ignore target for nonexistent variation' => [
                [
                    'variations' => ['a', 'b'],
                    'contextTargets' => [
                        ['contextKind' => 'kind1', 'variation' => 1, 'values' => ['key1']],
                    ]
                ],
                fn ($f) => $f->variations('a', 'b')
                    ->variationForKey('kind1', 'key1', 1)
                    ->variationForKey('kind1', 'key2', 3)
            ],
            'variationForUser is shortcut for variationForKey' => [
                [
                    'targets' => [
                        ['variation' => 0, 'values' => ['key1']]
                    ],
                    'contextTargets' => [
                        ['contextKind' => 'user', 'variation' => 0, 'values' => []]
                    ]
                ],
                fn ($f) => $f->variationForUser('key1', true)
            ],
            'clear targets' => [
                [],
                fn ($f) => $f->variationForKey('kind1', 'key1', 0)
                    ->clearTargets()
            ],
            'clearUserTargets is synonym for clearTargets' => [
                [],
                fn ($f) => $f->variationForKey('kind1', 'key1', 0)
                    ->clearUserTargets()
            ],
            'ifMatchContext' => [
                [
                    'rules' => [
                        [
                            'variation' => 1,
                            'id' => 'rule0',
                            'clauses' => [
                                ['contextKind' => 'kind1', 'attribute' => 'attr1', 'op' => 'in', 'values' => ['a', 'b'], 'negate' => false]
                            ]
                        ]
                    ]
                ],
                fn ($f) => $f->ifMatchContext('kind1', 'attr1', 'a', 'b')->thenReturn(1)
            ],
            'ifNotMatchContext' => [
                [
                    'rules' => [
                        [
                            'variation' => 1,
                            'id' => 'rule0',
                            'clauses' => [
                                ['contextKind' => 'kind1', 'attribute' => 'attr1', 'op' => 'in', 'values' => ['a', 'b'], 'negate' => true]
                            ]
                        ]
                    ]
                ],
                fn ($f) => $f->ifNotMatchContext('kind1', 'attr1', 'a', 'b')->thenReturn(1)
            ],
            'ifMatch is shortcut for ifMatchContext' => [
                [
                    'rules' => [
                        [
                            'variation' => 1,
                            'id' => 'rule0',
                            'clauses' => [
                                ['contextKind' => 'user', 'attribute' => 'attr1', 'op' => 'in', 'values' => ['a', 'b'], 'negate' => false]
                            ]
                        ]
                    ]
                ],
                fn ($f) => $f->ifMatch('attr1', 'a', 'b')->thenReturn(1)
            ],
            'ifNotMatch is shortcut for ifNotMatchContext' => [
                [
                    'rules' => [
                        [
                            'variation' => 1,
                            'id' => 'rule0',
                            'clauses' => [
                                ['contextKind' => 'user', 'attribute' => 'attr1', 'op' => 'in', 'values' => ['a', 'b'], 'negate' => true]
                            ]
                        ]
                    ]
                ],
                fn ($f) => $f->ifNotMatch('attr1', 'a', 'b')->thenReturn(1)
            ],
            'andMatchContext' => [
                [
                    'rules' => [
                        [
                            'variation' => 1,
                            'id' => 'rule0',
                            'clauses' => [
                                ['contextKind' => 'kind1', 'attribute' => 'attr1', 'op' => 'in', 'values' => ['a', 'b'], 'negate' => false],
                                ['contextKind' => 'kind1', 'attribute' => 'attr2', 'op' => 'in', 'values' => ['c', 'd'], 'negate' => false]
                            ]
                        ]
                    ]
                ],
                fn ($f) => $f->ifMatchContext('kind1', 'attr1', 'a', 'b')
                    ->andMatchContext('kind1', 'attr2', 'c', 'd')->thenReturn(1)
            ],
            'andNotMatchContext' => [
                [
                    'rules' => [
                        [
                            'variation' => 1,
                            'id' => 'rule0',
                            'clauses' => [
                                ['contextKind' => 'kind1', 'attribute' => 'attr1', 'op' => 'in', 'values' => ['a', 'b'], 'negate' => false],
                                ['contextKind' => 'kind1', 'attribute' => 'attr2', 'op' => 'in', 'values' => ['c', 'd'], 'negate' => true]
                            ]
                        ]
                    ]
                ],
                fn ($f) => $f->ifMatchContext('kind1', 'attr1', 'a', 'b')
                    ->andNotMatchContext('kind1', 'attr2', 'c', 'd')->thenReturn(1)
            ],
            'andMatch is shortcut for andMatchContext' => [
                [
                    'rules' => [
                        [
                            'variation' => 1,
                            'id' => 'rule0',
                            'clauses' => [
                                ['contextKind' => 'kind1', 'attribute' => 'attr1', 'op' => 'in', 'values' => ['a', 'b'], 'negate' => false],
                                ['contextKind' => 'user', 'attribute' => 'attr2', 'op' => 'in', 'values' => ['c', 'd'], 'negate' => false]
                            ]
                        ]
                    ]
                ],
                fn ($f) => $f->ifMatchContext('kind1', 'attr1', 'a', 'b')
                    ->andMatch('attr2', 'c', 'd')->thenReturn(1)
            ],
            'andNotMatch is shortcut for andNotMatchContext' => [
                [
                    'rules' => [
                        [
                            'variation' => 1,
                            'id' => 'rule0',
                            'clauses' => [
                                ['contextKind' => 'kind1', 'attribute' => 'attr1', 'op' => 'in', 'values' => ['a', 'b'], 'negate' => false],
                                ['contextKind' => 'user', 'attribute' => 'attr2', 'op' => 'in', 'values' => ['c', 'd'], 'negate' => true]
                            ]
                        ]
                    ]
                ],
                fn ($f) => $f->ifMatchContext('kind1', 'attr1', 'a', 'b')
                    ->andNotMatch('attr2', 'c', 'd')->thenReturn(1)
            ],
            'clearRules' => [
                [],
                fn ($f) => $f->ifMatch('kind1', 'attr1', 'a')->thenReturn(1)->clearRules()
            ]
        ];
    }

    /**
     * @dataProvider flagConfigParameterizedTestParams
     */
    public function testFlagConfigParameterized($expected, $builderActions)
    {
        $td = new TestData();
        $flagBuilder = $builderActions($td->flag("flagkey"));
        $actual = $flagBuilder->build(1);
        $allExpected = array_merge($this->defaultFlagProps(), $expected);
        $this->assertEquals($allExpected, $actual);
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
            'salt' => '',
            'prerequisites' => [],
        ];
        $expectedFeatureFlag = FeatureFlag::decode($expectedFlagJson);

        $td = new TestData();
        $flag = $td->flag($key);
        $td->update($flag);
        $featureFlag = $td->getFeature($key);
        $this->assertEquals($expectedFeatureFlag, $featureFlag);
    }

    public function testCanSetAndResetFeatureFlag()
    {
        $key = 'test-flag';
        $expectedUpdatedFlagJson = [
            'key' => $key,
            'version' => 2,
            'deleted' => false,
            'on' => true,
            'targets' => [],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => ['variation' => 2],
            'variations' => ['red', 'amber', 'green'],

            /* Required FeatureFlag fields */
            'salt' => '',
            'prerequisites' => [],
        ];
        $expectedUpdatedFeatureFlag = FeatureFlag::decode($expectedUpdatedFlagJson);

        $td = new TestData();
        $flag = $td->flag($key);
        $td->update($flag);
        
        $updatedFlag = $flag->variations('red', 'amber', 'green')->fallthroughVariation(2);
        $td->update($updatedFlag);

        $featureFlag = $td->getFeature($key);
        $this->assertEquals($expectedUpdatedFeatureFlag, $featureFlag);
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

        $context1 = LDContext::builder("x")->set("firstName", "Janet")->set("lastName", "Cline")->build();
        $this->assertFalse($client->variation("flag", $context1));

        $context2 = LDContext::builder("x")->set("firstName", "Patsy")->set("lastName", "Cline")->build();
        $this->assertFalse($client->variation("flag", $context2));

        $context3 = LDContext::builder("x")->set("firstName", "Patsy")->set("lastName", "Smith")->build();
        $this->assertTrue($client->variation("flag", $context3));
    }
}
