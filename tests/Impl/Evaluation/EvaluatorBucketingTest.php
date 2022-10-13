<?php

namespace LaunchDarkly\Tests\Impl\Evaluation;

use LaunchDarkly\Impl\Evaluation\EvaluatorBucketing;
use LaunchDarkly\LDContext;
use PHPUnit\Framework\TestCase;

class EvaluatorBucketingTest extends TestCase
{
    public function testUsingSeedIsDifferentThanSalt()
    {
        $seed = 357;
        $context = LDContext::create('userkey');
        $key = 'flag-key';
        $attr = 'key';
        $salt = 'testing123';
        $contextPoint1 = EvaluatorBucketing::getBucketValueForContext($context, $key, $attr, $salt, null);
        $contextPoint2 = EvaluatorBucketing::getBucketValueForContext($context, $key, $attr, $salt, $seed);

        $this->assertNotEquals($contextPoint1, $contextPoint2);
    }

    public function testDifferentSaltsProduceDifferentAssignment()
    {
        $seed1 = 357;
        $seed2 = 13;
        $context = LDContext::create('userkey');
        $key = 'flag-key';
        $attr = 'key';
        $salt = 'testing123';
        $contextPoint1 = EvaluatorBucketing::getBucketValueForContext($context, $key, $attr, $salt, $seed1);
        $contextPoint2 = EvaluatorBucketing::getBucketValueForContext($context, $key, $attr, $salt, $seed2);

        $this->assertNotEquals($contextPoint1, $contextPoint2);
    }

    public function testSameSeedIsDeterministic()
    {
        $seed = 357;
        $context = LDContext::create('userkey');
        $key = 'flag-key';
        $attr = 'key';
        $salt = 'testing123';
        $contextPoint1 = EvaluatorBucketing::getBucketValueForContext($context, $key, $attr, $salt, $seed);
        $contextPoint2 = EvaluatorBucketing::getBucketValueForContext($context, $key, $attr, $salt, $seed);

        $this->assertEquals($contextPoint1, $contextPoint2);
    }
}
