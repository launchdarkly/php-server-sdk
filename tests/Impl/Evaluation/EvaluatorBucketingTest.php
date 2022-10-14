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
        $contextPoint1 = EvaluatorBucketing::getBucketValueForContext($context, null, $key, $attr, $salt, null);
        $contextPoint2 = EvaluatorBucketing::getBucketValueForContext($context, null, $key, $attr, $salt, $seed);

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
        $contextPoint1 = EvaluatorBucketing::getBucketValueForContext($context, null, $key, $attr, $salt, $seed1);
        $contextPoint2 = EvaluatorBucketing::getBucketValueForContext($context, null, $key, $attr, $salt, $seed2);

        $this->assertNotEquals($contextPoint1, $contextPoint2);
    }

    public function testSameSeedIsDeterministic()
    {
        $seed = 357;
        $context = LDContext::create('userkey');
        $key = 'flag-key';
        $attr = 'key';
        $salt = 'testing123';
        $contextPoint1 = EvaluatorBucketing::getBucketValueForContext($context, null, $key, $attr, $salt, $seed);
        $contextPoint2 = EvaluatorBucketing::getBucketValueForContext($context, null, $key, $attr, $salt, $seed);

        $this->assertEquals($contextPoint1, $contextPoint2);
    }

    public function testContextKindSelectsContext()
    {
        $seed = 357;
        $context1 = LDContext::create('key1');
        $context2 = LDContext::create('key2', 'kind2');
        $multi = LDContext::createMulti($context1, $context2);

        $key = 'flag-key';
        $attr = 'key';
        $salt = 'testing123';

        $this->assertEquals(
            EvaluatorBucketing::getBucketValueForContext($context1, null, $key, $attr, $salt, $seed),
            EvaluatorBucketing::getBucketValueForContext($context1, 'user', $key, $attr, $salt, $seed)
        );
        $this->assertEquals(
            EvaluatorBucketing::getBucketValueForContext($context1, null, $key, $attr, $salt, $seed),
            EvaluatorBucketing::getBucketValueForContext($multi, 'user', $key, $attr, $salt, $seed)
        );
        $this->assertEquals(
            EvaluatorBucketing::getBucketValueForContext($context2, 'kind2', $key, $attr, $salt, $seed),
            EvaluatorBucketing::getBucketValueForContext($multi, 'kind2', $key, $attr, $salt, $seed)
        );
        $this->assertNotEquals(
            EvaluatorBucketing::getBucketValueForContext($multi, 'user', $key, $attr, $salt, $seed),
            EvaluatorBucketing::getBucketValueForContext($multi, 'kind2', $key, $attr, $salt, $seed)
        );
    }
}
