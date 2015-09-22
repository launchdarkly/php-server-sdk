<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\FeatureRep;
use LaunchDarkly\LDUserBuilder;
use LaunchDarkly\LDUser;
use LaunchDarkly\TargetRule;
use LaunchDarkly\Variation;

class FeatureRepTest extends \PHPUnit_Framework_TestCase {

    /** @var FeatureRep */
    protected $_simpleFlag   = null;
    /** @var FeatureRep */
    protected $_disabledFlag = null;
    /** @var FeatureRep */
    protected $_userTargetFlag = null;

    protected function setUp() {
        parent::setUp();
        $targetUserOn = new TargetRule("key", "in", array("targetOn@test.com"));
        $targetGroupOn = new TargetRule("groups", "in", array("google", "microsoft"));
        $targetUserOff = new TargetRule("key", "in", array("targetOff@test.com"));
        $targetGroupOff = new TargetRule("groups", "in", array("oracle"));
        $targetEmailOn = new TargetRule("email", "in", array("targetEmailOn@test.com"));

        $trueVariation = new Variation(true, 80, array($targetUserOn, $targetGroupOn, $targetEmailOn), null);
        $falseVariation = new Variation(false, 20, array($targetUserOff, $targetGroupOff), null);

        $this->_simpleFlag   = new FeatureRep("Sample flag", "sample.flag", "feefifofum", true,  array($trueVariation, $falseVariation));
        $this->_disabledFlag = new FeatureRep("Sample flag", "sample.flag", "feefifofum", false, array($trueVariation, $falseVariation));

        $userTargetVariation = new Variation(false, 20, array(), $targetUserOn);

        $this->_userTargetFlag = new FeatureRep("Sample flag", "sample.flag", "feefifofum", true, array($trueVariation, $userTargetVariation));
    }

    protected function tearDown() {
        parent::tearDown();
        $this->_simpleFlag = null;
    }

    public function testFlagForTargetedUserOff() {
        $user = new LDUser("targetOff@test.com");
        $b = $this->_simpleFlag->evaluate($user);
        $this->assertEquals(false, $b);
    }

    public function testFlagForTargetedUserOn() {
        $user = new LDUser("targetOn@test.com");
        $b = $this->_simpleFlag->evaluate($user);
        $this->assertEquals(true, $b);
    }

    public function testFlagForTargetGroupOn() {
        $builder = new LDUserBuilder("targetOther@test.com");
        $user = $builder->custom(array("groups" => array("google", "microsoft")))->build();
        $b = $this->_simpleFlag->evaluate($user);
        $this->assertEquals(true, $b);
    }

    public function testFlagForTargetGroupOff() {
        $builder = new LDUserBuilder("targetOther@test.com");
        $user = $builder->custom(array("groups" => array("oracle")))->build();
        $b = $this->_simpleFlag->evaluate($user);
        $this->assertEquals(false, $b);
    }

    public function testDisabledFlagAlwaysOff() {
        $user = new LDUser("targetOn@test.com");
        $b = $this->_disabledFlag->evaluate($user);
        $this->assertEquals(null, $b);
    }

    public function testUserRuleFlagForTargetUserOff() {
        $builder = new LDUserBuilder("targetOff@test.com");
        $user = $builder->build();
        $b = $this->_userTargetFlag->evaluate($user);
        $this->assertEquals(false, $b);
    }

    public function testFlagForTargetEmailOff() {
        $builder = new LDUserBuilder("targetOff@test.com");
        $user = $builder->email("targetEmailOn@test.com")->build();
        $b = $this->_simpleFlag->evaluate($user);
        $this->assertEquals(true,$b);
    }
}

