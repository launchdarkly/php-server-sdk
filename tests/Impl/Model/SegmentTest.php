<?php

namespace LaunchDarkly\Tests\Impl\Model;

use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\LDUserBuilder;
use PHPUnit\Framework\TestCase;

$defaultUser = (new LDUserBuilder('foo'))->build();

function makeSegmentMatchingUser($user, $ruleAttrs = [])
{
    $clause = ['attribute' => 'key', 'op' => 'in', 'values' => [$user->getKey()], 'negate' => false];
    $rule = array_merge(['clauses' => [$clause]], $ruleAttrs);
    $json = [
        'key' => 'test',
        'included' => [],
        'excluded' => [],
        'salt' => 'salt',
        'rules' => [$rule],
        'version' => 1,
        'deleted' => false
    ];
    return Segment::decode($json);
}

class SegmentTest extends TestCase
{
    public function testExplicitIncludeUser()
    {
        global $defaultUser;
        $json = [
            'key' => 'test',
            'included' => [$defaultUser->getKey()],
            'excluded' => [],
            'rules' => [],
            'salt' => 'salt',
            'version' => 1,
            'deleted' => false
        ];
        $segment = Segment::decode($json);
        $this->assertTrue($segment->matchesUser($defaultUser));
    }

    public function testExplicitExcludeUser()
    {
        global $defaultUser;
        $json = [
            'key' => 'test',
            'included' => [],
            'excluded' => [$defaultUser->getKey()],
            'rules' => [],
            'salt' => 'salt',
            'version' => 1,
            'deleted' => false
        ];
        $segment = Segment::decode($json);
        $this->assertFalse($segment->matchesUser($defaultUser));
    }

    public function testExplicitIncludePasPrecedence()
    {
        global $defaultUser;
        $json = [
            'key' => 'test',
            'included' => [$defaultUser->getKey()],
            'excluded' => [$defaultUser->getKey()],
            'rules' => [],
            'salt' => 'salt',
            'version' => 1,
            'deleted' => false
        ];
        $segment = Segment::decode($json);
        $ub = new LDUserBuilder('foo');
        $this->assertTrue($segment->matchesUser($ub->build()));
    }

    public function testMatchingRuleWithFullRollout()
    {
        global $defaultUser;
        $segment = makeSegmentMatchingUser($defaultUser, ['weight' => 100000]);
        $this->assertTrue($segment->matchesUser($defaultUser));
    }

    public function testMatchingRuleWithZeroRollout()
    {
        global $defaultUser;
        $segment = makeSegmentMatchingUser($defaultUser, ['weight' => 0]);
        $this->assertFalse($segment->matchesUser($defaultUser));
    }

    public function testRolloutCalculationCanBucketByKey()
    {
        $user = (new LDUserBuilder('userkey'))->name('Bob')->build();
        $this->verifyRollout($user, 12551);
    }

    public function testRolloutCalculationIncludesSecondaryKey()
    {
        $user = (new LDUserBuilder('userkey'))->secondary('999')->build();
        $this->verifyRollout($user, 81650);
    }

    public function testRolloutCalculationCoercesSecondaryKeyToString()
    {
        $user = (new LDUserBuilder('userkey'))->secondary(999)->build();
        $this->verifyRollout($user, 81650);
    }

    public function testRolloutCalculationCanBucketBySpecificAttribute()
    {
        $user = (new LDUserBuilder('userkey'))->name('Bob')->build();
        $this->verifyRollout($user, 61691, ['bucketBy' => 'name']);
    }

    private function verifyRollout($user, $expectedBucketValue, $rolloutAttrs = [])
    {
        $segment0 = makeSegmentMatchingUser($user, array_merge(['weight' => $expectedBucketValue + 1], $rolloutAttrs));
        $this->assertTrue($segment0->matchesUser($user));
        $segment1 = makeSegmentMatchingUser($user, array_merge(['weight' => $expectedBucketValue], $rolloutAttrs));
        $this->assertFalse($segment1->matchesUser($user));
    }

    public function testMatchingRuleWithMultipleClauses()
    {
        $json = [
            'key' => 'test',
            'included' => [],
            'excluded' => [],
            'salt' => 'salt',
            'rules' => [
                [
                    'clauses' => [
                        [
                            'attribute' => 'email',
                            'op' => 'in',
                            'values' => ['test@example.com'],
                            'negate' => false
                        ],
                        [
                            'attribute' => 'name',
                            'op' => 'in',
                            'values' => ['bob'],
                            'negate' => false
                        ]
                    ],
                    'weight' => 100000
                ]
            ],
            'version' => 1,
            'deleted' => false
        ];
        $segment = Segment::decode($json);
        $ub = new LDUserBuilder('foo');
        $ub->email('test@example.com');
        $ub->name('bob');
        $this->assertTrue($segment->matchesUser($ub->build()));
    }

    public function testNonMatchingRuleWithMultipleClauses()
    {
        $json = [
            'key' => 'test',
            'included' => [],
            'excluded' => [],
            'salt' => 'salt',
            'rules' => [
                [
                    'clauses' => [
                        [
                            'attribute' => 'email',
                            'op' => 'in',
                            'values' => ['test@example.com'],
                            'negate' => false
                        ],
                        [
                            'attribute' => 'name',
                            'op' => 'in',
                            'values' => ['bill'],
                            'negate' => false
                        ]
                    ],
                    'weight' => 100000
                ]
            ],
            'version' => 1,
            'deleted' => false
        ];
        $segment = Segment::decode($json);
        $ub = new LDUserBuilder('foo');
        $ub->email('test@example.com');
        $ub->name('bob');
        $this->assertFalse($segment->matchesUser($ub->build()));
    }
}
