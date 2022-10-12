<?php

namespace LaunchDarkly\Tests\Impl\Model;

use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\LDContext;
use PHPUnit\Framework\TestCase;

$defaultContext = LDContext::create('foo');

function makeSegmentMatchingContext($context, $ruleAttrs = [])
{
    $clause = ['attribute' => 'key', 'op' => 'in', 'values' => [$context->getKey()], 'negate' => false];
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
    public function testExplicitIncludeContext()
    {
        global $defaultContext;
        $json = [
            'key' => 'test',
            'included' => [$defaultContext->getKey()],
            'excluded' => [],
            'rules' => [],
            'salt' => 'salt',
            'version' => 1,
            'deleted' => false
        ];
        $segment = Segment::decode($json);
        $this->assertTrue($segment->matchesContext($defaultContext));
    }

    public function testExplicitExcludeContext()
    {
        global $defaultContext;
        $json = [
            'key' => 'test',
            'included' => [],
            'excluded' => [$defaultContext->getKey()],
            'rules' => [],
            'salt' => 'salt',
            'version' => 1,
            'deleted' => false
        ];
        $segment = Segment::decode($json);
        $this->assertFalse($segment->matchesContext($defaultContext));
    }

    public function testExplicitIncludeHasPrecedence()
    {
        global $defaultContext;
        $json = [
            'key' => 'test',
            'included' => [$defaultContext->getKey()],
            'excluded' => [$defaultContext->getKey()],
            'rules' => [],
            'salt' => 'salt',
            'version' => 1,
            'deleted' => false
        ];
        $segment = Segment::decode($json);
        $this->assertTrue($segment->matchesContext($defaultContext));
    }

    public function testMatchingRuleWithFullRollout()
    {
        global $defaultContext;
        $segment = makeSegmentMatchingContext($defaultContext, ['weight' => 100000]);
        $this->assertTrue($segment->matchesContext($defaultContext));
    }

    public function testMatchingRuleWithZeroRollout()
    {
        global $defaultContext;
        $segment = makeSegmentMatchingContext($defaultContext, ['weight' => 0]);
        $this->assertFalse($segment->matchesContext($defaultContext));
    }

    public function testRolloutCalculationCanBucketByKey()
    {
        $context = LDContext::builder('userkey')->name('Bob')->build();
        $this->verifyRollout($context, 12551);
    }

    public function testRolloutCalculationCanBucketBySpecificAttribute()
    {
        $context = LDContext::builder('userkey')->name('Bob')->build();
        $this->verifyRollout($context, 61691, ['bucketBy' => 'name']);
    }

    private function verifyRollout($context, $expectedBucketValue, $rolloutAttrs = [])
    {
        $segment0 = makeSegmentMatchingContext($context, array_merge(['weight' => $expectedBucketValue + 1], $rolloutAttrs));
        $this->assertTrue($segment0->matchesContext($context));
        $segment1 = makeSegmentMatchingContext($context, array_merge(['weight' => $expectedBucketValue], $rolloutAttrs));
        $this->assertFalse($segment1->matchesContext($context));
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
        $context = LDContext::builder('foo')->name('bob')->set('email', 'test@example.com')->build();
        $this->assertTrue($segment->matchesContext($context));
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
        $context = LDContext::builder('foo')->name('bob')->set('email', 'test@example.com')->build();
        $this->assertFalse($segment->matchesContext($context));
    }
}
