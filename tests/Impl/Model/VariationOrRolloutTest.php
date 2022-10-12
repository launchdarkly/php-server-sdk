<?php

namespace LaunchDarkly\Tests\Impl\Model;

use LaunchDarkly\Impl\Model\VariationOrRollout;
use LaunchDarkly\LDContext;
use PHPUnit\Framework\TestCase;

class VariationOrRolloutTest extends TestCase
{
    public function testUsingSeedIsDifferentThanSalt()
    {
        $seed = 357;
        $vr = ['rollout' => [
            'variations' => [
                ['variation' => 1, 'weight' => 50000],
                ['variation' => 2, 'weight' => 50000]
            ]
        ]];

        $decodedVr = call_user_func(VariationOrRollout::getDecoder(), $vr);

        $context = LDContext::create('userkey');
        $key = 'flag-key';
        $attr = 'key';
        $salt = 'testing123';
        $contextPoint1 = $decodedVr->bucketContext($context, $key, $attr, $salt, null);
        $contextPoint2 = $decodedVr->bucketContext($context, $key, $attr, $salt, $seed);

        $this->assertNotEquals($contextPoint1, $contextPoint2);
    }

    public function testDifferentSaltsProduceDifferentAssignment()
    {
        $seed1 = 357;
        $seed2 = 13;
        $vr = ['rollout' => [
            'variations' => [
                ['variation' => 1, 'weight' => 50000],
                ['variation' => 2, 'weight' => 50000]
            ]
        ]];

        $decodedVr = call_user_func(VariationOrRollout::getDecoder(), $vr);

        $context = LDContext::create('userkey');
        $key = 'flag-key';
        $attr = 'key';
        $salt = 'testing123';
        $contextPoint1 = $decodedVr->bucketContext($context, $key, $attr, $salt, $seed1);
        $contextPoint2 = $decodedVr->bucketContext($context, $key, $attr, $salt, $seed2);

        $this->assertNotEquals($contextPoint1, $contextPoint2);
    }

    public function testSameSeedIsDeterministic()
    {
        $seed = 357;
        $vr = ['rollout' => [
            'variations' => [
                ['variation' => 1, 'weight' => 50000],
                ['variation' => 2, 'weight' => 50000]
            ]
        ]];

        $decodedVr = call_user_func(VariationOrRollout::getDecoder(), $vr);

        $context = LDContext::create('userkey');
        $key = 'flag-key';
        $attr = 'key';
        $salt = 'testing123';
        $contextPoint1 = $decodedVr->bucketContext($context, $key, $attr, $salt, $seed);
        $contextPoint2 = $decodedVr->bucketContext($context, $key, $attr, $salt, $seed);

        $this->assertEquals($contextPoint1, $contextPoint2);
    }
}
