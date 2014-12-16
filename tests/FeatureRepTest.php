<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\FeatureRep;
use LaunchDarkly\LDUser;
use LaunchDarkly\TargetRule;
use LaunchDarkly\Variation;

class FeatureRepTest extends \PHPUnit_Framework_TestCase {

    protected $_simpleFlag   = null;
    protected $_disabledFlag = null;

    protected function setUp() {
        parent::setUp();
        $targetUserOn = new TargetRule("key", "in", ["targetOn@test.com"]);
        $targetGroupOn = new TargetRule("groups", "in", ["google", "microsoft"]);
        $targetUserOff = new TargetRule("key", "in", ["targetOff@test.com"]);
        $targetGroupOff = new TargetRule("groups", "in", ["oracle"]);

        $trueVariation = new Variation(true, 80, [$targetUserOn, $targetGroupOn]);
        $falseVariation = new Variation(false, 20, [$targetUserOff, $targetGroupOff]);

        $this->_simpleFlag   = new FeatureRep("Sample flag", "sample.flag", "feefifofum", true,  [$trueVariation, $falseVariation]);
        $this->_disabledFlag = new FeatureRep("Sample flag", "sample.flag", "feefifofum", false, [$trueVariation, $falseVariation]);
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
        $user = new LDUser("targetOther@test.com", null, null, null, ["groups" => ["google", "microsoft"]]);
        $b = $this->_simpleFlag->evaluate($user);
        $this->assertEquals(true, $b);
    }

    public function testFlagForTargetGroupOff() {
        $user = new LDUser("targetOther@test.com", null, null, null, ["groups" => "oracle"]);
        $b = $this->_simpleFlag->evaluate($user);
        $this->assertEquals(false, $b);
    }

    public function testDisabledFlagAlwaysOff() {
        $user = new LDUser("targetOn@test.com");
        $b = $this->_disabledFlag->evaluate($user);
        $this->assertEquals(null, $b);
    }
}

