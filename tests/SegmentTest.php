<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\LDUserBuilder;
use LaunchDarkly\Segment;
use PHPUnit\Framework\TestCase;

$defaultUser = (new LDUserBuilder('foo'))->build();

function makeSegmentMatchingUser($user, $ruleAttrs = array())
{
    $clause = array('attribute' => 'key', 'op' => 'in', 'values' => array($user->getKey()), 'negate' => false);
    $rule = array_merge(array('clauses' => array($clause)), $ruleAttrs);
    $json = array(
        'key' => 'test',
        'included' => array(),
        'excluded' => array(),
        'salt' => 'salt',
        'rules' => array($rule),
        'version' => 1,
        'deleted' => false
    );
    return Segment::decode($json);
}

class SegmentTest extends \PHPUnit\Framework\TestCase
{
    public function testExplicitIncludeUser()
    {
        global $defaultUser;
        $json = array(
            'key' => 'test',
            'included' => array($defaultUser->getKey()),
            'excluded' => array(),
            'rules' => array(),
            'salt' => 'salt',
            'version' => 1,
            'deleted' => false
        );
        $segment = Segment::decode($json);
        $this->assertTrue($segment->matchesUser($defaultUser));
    }

    public function testExplicitExcludeUser()
    {
        global $defaultUser;
        $json = array(
            'key' => 'test',
            'included' => array(),
            'excluded' => array($defaultUser->getKey()),
            'rules' => array(),
            'salt' => 'salt',
            'version' => 1,
            'deleted' => false
        );
        $segment = Segment::decode($json);
        $this->assertFalse($segment->matchesUser($defaultUser));
    }

    public function testExplicitIncludePasPrecedence()
    {
        global $defaultUser;
        $json = array(
            'key' => 'test',
            'included' => array($defaultUser->getKey()),
            'excluded' => array($defaultUser->getKey()),
            'rules' => array(),
            'salt' => 'salt',
            'version' => 1,
            'deleted' => false
        );
        $segment = Segment::decode($json);
        $ub = new LDUserBuilder('foo');
        $this->assertTrue($segment->matchesUser($ub->build()));
    }

    public function testMatchingRuleWithFullRollout()
    {
        global $defaultUser;
        $segment = makeSegmentMatchingUser($defaultUser, array('weight' => 100000));
        $this->assertTrue($segment->matchesUser($defaultUser));
    }

    public function testMatchingRuleWithZeroRollout()
    {
        global $defaultUser;
        $segment = makeSegmentMatchingUser($defaultUser, array('weight' => 0));
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
        $this->verifyRollout($user, 61691, array('bucketBy' => 'name'));
    }

    private function verifyRollout($user, $expectedBucketValue, $rolloutAttrs = array())
    {
        $segment0 = makeSegmentMatchingUser($user, array_merge(array('weight' => $expectedBucketValue + 1), $rolloutAttrs));
        $this->assertTrue($segment0->matchesUser($user));
        $segment1 = makeSegmentMatchingUser($user, array_merge(array('weight' => $expectedBucketValue), $rolloutAttrs));
        $this->assertFalse($segment1->matchesUser($user));
    }

    public function testMatchingRuleWithMultipleClauses()
    {
        $json = array(
            'key' => 'test',
            'included' => array(),
            'excluded' => array(),
            'salt' => 'salt',
            'rules' => array(
                array(
                    'clauses' => array(
                        array(
                            'attribute' => 'email',
                            'op' => 'in',
                            'values' => array('test@example.com'),
                            'negate' => false
                        ),
                        array(
                            'attribute' => 'name',
                            'op' => 'in',
                            'values' => array('bob'),
                            'negate' => false
                        )
                    ),
                    'weight' => 100000
                )
            ),
            'version' => 1,
            'deleted' => false
        );
        $segment = Segment::decode($json);
        $ub = new LDUserBuilder('foo');
        $ub->email('test@example.com');
        $ub->name('bob');
        $this->assertTrue($segment->matchesUser($ub->build()));
    }

    public function testNonMatchingRuleWithMultipleClauses()
    {
        $json = array(
            'key' => 'test',
            'included' => array(),
            'excluded' => array(),
            'salt' => 'salt',
            'rules' => array(
                array(
                    'clauses' => array(
                        array(
                            'attribute' => 'email',
                            'op' => 'in',
                            'values' => array('test@example.com'),
                            'negate' => false
                        ),
                        array(
                            'attribute' => 'name',
                            'op' => 'in',
                            'values' => array('bill'),
                            'negate' => false
                        )
                    ),
                    'weight' => 100000
                )
            ),
            'version' => 1,
            'deleted' => false
        );
        $segment = Segment::decode($json);
        $ub = new LDUserBuilder('foo');
        $ub->email('test@example.com');
        $ub->name('bob');
        $this->assertFalse($segment->matchesUser($ub->build()));
    }
}
