<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\FeatureRep;
use LaunchDarkly\LDUserBuilder;
use LaunchDarkly\LDUser;
use LaunchDarkly\TargetRule;
use LaunchDarkly\Variation;

class FeatureRepTest extends \PHPUnit_Framework_TestCase {

    protected $_simpleFlag   = null;
    protected $_disabledFlag = null;
    protected $_userTargetFlag = null;

    protected function setUp() {
        parent::setUp();
        $targetUserOn = new TargetRule("key", "in", ["targetOn@test.com"]);
        $targetGroupOn = new TargetRule("groups", "in", ["google", "microsoft"]);
        $targetUserOff = new TargetRule("key", "in", ["targetOff@test.com"]);
        $targetGroupOff = new TargetRule("groups", "in", ["oracle"]);
        $targetEmailOn = new TargetRule("email", "in", ["targetEmailOn@test.com"]);

        $trueVariation = new Variation(true, 80, [$targetUserOn, $targetGroupOn, $targetEmailOn], null);
        $falseVariation = new Variation(false, 20, [$targetUserOff, $targetGroupOff], null);

        $this->_simpleFlag   = new FeatureRep("Sample flag", "sample.flag", "feefifofum", true,  [$trueVariation, $falseVariation]);
        $this->_disabledFlag = new FeatureRep("Sample flag", "sample.flag", "feefifofum", false, [$trueVariation, $falseVariation]);

        $userTargetVariation = new Variation(false, 20, [], $targetUserOn);

        $this->_userTargetFlag = new FeatureRep("Sample flag", "sample.flag", "feefifofum", true, [$trueVariation, $userTargetVariation]);
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
        $user = (new LDUserBuilder("targetOther@test.com"))->custom(["groups" => ["google", "microsoft"]])->build();
        $b = $this->_simpleFlag->evaluate($user);
        $this->assertEquals(true, $b);
    }

    public function testFlagForTargetGroupOff() {
        $user = new LDUser("targetOther@test.com", null, null, null, null, null, null, null, null, ["groups" => "oracle"]);
        $b = $this->_simpleFlag->evaluate($user);
        $this->assertEquals(false, $b);
    }

    public function testDisabledFlagAlwaysOff() {
        $user = new LDUser("targetOn@test.com");
        $b = $this->_disabledFlag->evaluate($user);
        $this->assertEquals(null, $b);
    }

    public function testUserRuleFlagForTargetUserOff() {
        $user = (new LDUserBuilder("targetOff@test.com"))->build();
        $b = $this->_userTargetFlag->evaluate($user);
        $this->assertEquals(false, $b);
    }

    public function testFlagForTargetEmailOff() {
        $user = (new LDUserBuilder("targetOff@test.com"))->email("targetEmailOn@test.com")->build();
        $b = $this->_simpleFlag->evaluate($user);
        $this->assertEquals(true,$b);
    }
}

