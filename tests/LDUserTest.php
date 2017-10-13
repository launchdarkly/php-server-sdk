<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\LDUserBuilder;

class LDUserTest extends \PHPUnit_Framework_TestCase {

    public function testLDUserKey() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->build();
        $this->assertEquals("foo@bar.com", $user->getKey());
    }

    public function testCoerceLDUserKey() {
        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $this->assertEquals("string", gettype($user->getKey()));
    }

    public function testEmptyCustom() {        
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->build();
    }

    public function testLDUserSecondary() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->secondary("secondary")->build();
        $this->assertEquals("secondary", $user->getSecondary());
    }

    public function testLDUserIP() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->ip("127.0.0.1")->build();
        $this->assertEquals("127.0.0.1", $user->getIP());
    }

    public function testLDUserCountry() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->country("US")->build();
        $this->assertEquals("US", $user->getCountry());
    }

    public function testLDUserEmail() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->email("foo+test@bar.com")->build();
        $this->assertEquals("foo+test@bar.com", $user->getEmail());
    }

    public function testLDUserName() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->name("Foo Bar")->build();
        $this->assertEquals("Foo Bar", $user->getName());
    }

    public function testLDUserAvatar() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->avatar("http://www.gravatar.com/avatar/1")->build();
        $this->assertEquals("http://www.gravatar.com/avatar/1", $user->getAvatar());
    }

    public function testLDUserFirstName() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->firstName("Foo")->build();
        $this->assertEquals("Foo", $user->getFirstName());
    }

    public function testLDUserLastName() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->lastName("Bar")->build();
        $this->assertEquals("Bar", $user->getLastName());
    }

    public function testLDUserAnonymous() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->anonymous(true)->build();
        $this->assertEquals(true, $user->getAnonymous());
    }

    public function testLDUserBlankKey() {
        $builder = new LDUserBuilder("");
        $user = $builder->build();
        $this->assertTrue($user->isKeyBlank());
        $this->assertFalse(is_null($user->getKey()));

        $builder = new LDUserBuilder("key");
        $user = $builder->build();
        $this->assertFalse($user->isKeyBlank());

        $builder = new LDUserBuilder(null);
        $user = $builder->build();
        $this->assertFalse($user->isKeyBlank());
    }
}
