<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\LDUserBuilder;
use LaunchDarkly\VariationOrRollout;

class VariationOrRolloutTest extends \PHPUnit_Framework_TestCase
{
    public function testUsingSeedIsDifferentThanSalt()
    {
        $seed = 357;
        $vr = array('rollout' => array(
            'variations' => array(
                array('variation' => 1, 'weight' => 50000),
                array('variation' => 2, 'weight' => 50000)
            )
        ));

        $decodedVr = call_user_func(VariationOrRollout::getDecoder(), $vr);

        $ub = new LDUserBuilder('userkey');
        $user = $ub->build();
        $key = 'flag-key';
        $attr = 'key';
        $salt = 'testing123';
        $userPoint1 = $decodedVr->bucketUser($user, $key, $attr, $salt, null);
        $userPoint2 = $decodedVr->bucketUser($user, $key, $attr, $salt, $seed);

        $this->assertNotEquals($userPoint1, $userPoint2);
    }

    public function testDifferentSaltsProduceDifferentAssignment()
    {
        $seed1 = 357;
        $seed2 = 13;
        $vr = array('rollout' => array(
            'variations' => array(
                array('variation' => 1, 'weight' => 50000),
                array('variation' => 2, 'weight' => 50000)
            )
        ));

        $decodedVr = call_user_func(VariationOrRollout::getDecoder(), $vr);

        $ub = new LDUserBuilder('userkey');
        $user = $ub->build();
        $key = 'flag-key';
        $attr = 'key';
        $salt = 'testing123';
        $userPoint1 = $decodedVr->bucketUser($user, $key, $attr, $salt, $seed1);
        $userPoint2 = $decodedVr->bucketUser($user, $key, $attr, $salt, $seed2);

        $this->assertNotEquals($userPoint1, $userPoint2);
    }

    public function testSameSeedIsDeterministic()
    {
        $seed = 357;
        $vr = array('rollout' => array(
            'variations' => array(
                array('variation' => 1, 'weight' => 50000),
                array('variation' => 2, 'weight' => 50000)
            )
        ));

        $decodedVr = call_user_func(VariationOrRollout::getDecoder(), $vr);

        $ub = new LDUserBuilder('userkey');
        $user = $ub->build();
        $key = 'flag-key';
        $attr = 'key';
        $salt = 'testing123';
        $userPoint1 = $decodedVr->bucketUser($user, $key, $attr, $salt, $seed);
        $userPoint2 = $decodedVr->bucketUser($user, $key, $attr, $salt, $seed);

        $this->assertEquals($userPoint1, $userPoint2);
    }
}
