<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\LDUserBuilder;

class LDUserTest extends \PHPUnit_Framework_TestCase {

    public function testLDUserKey() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->build();
        $this->assertEquals("foo@bar.com", $user->getKey());
        $json = $user->toJSON();
        $this->assertEquals("foo@bar.com", $json['key']);
    }

    public function testCoerceLDUserKey() {
        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $this->assertEquals("string", gettype($user->getKey()));
        $json = $user->toJSON();
        $this->assertEquals("string", gettype($json['key']));
    }

    public function testLDUserSecondary() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->secondary("secondary")->build();
        $this->assertEquals("secondary", $user->getSecondary());
        $json = $user->toJSON();
        $this->assertEquals("secondary", $json['secondary']);
    }

    public function testLDUserIP() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->ip("127.0.0.1")->build();
        $this->assertEquals("127.0.0.1", $user->getIP());
        $json = $user->toJSON();
        $this->assertEquals("127.0.0.1", $json['ip']);
    }

    public function testLDUserCountry() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->country("US")->build();
        $this->assertEquals("US", $user->getCountry());
        $json = $user->toJSON();
        $this->assertEquals("US", $json['country']);
    }

    public function testLDUserEmail() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->email("foo+test@bar.com")->build();
        $this->assertEquals("foo+test@bar.com", $user->getEmail());
        $json = $user->toJSON();
        $this->assertEquals("foo+test@bar.com", $json['email']);
    }

    public function testLDUserName() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->name("Foo Bar")->build();
        $this->assertEquals("Foo Bar", $user->getName());
        $json = $user->toJSON();
        $this->assertEquals("Foo Bar", $json['name']);
    }

    public function testLDUserAvatar() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->avatar("http://www.gravatar.com/avatar/1")->build();
        $this->assertEquals("http://www.gravatar.com/avatar/1", $user->getAvatar());
        $json = $user->toJSON();
        $this->assertEquals("http://www.gravatar.com/avatar/1", $json['avatar']);
    }

    public function testLDUserFirstName() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->firstName("Foo")->build();
        $this->assertEquals("Foo", $user->getFirstName());
        $json = $user->toJSON();
        $this->assertEquals("Foo", $json['firstName']);
    }

    public function testLDUserLastName() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->lastName("Bar")->build();
        $this->assertEquals("Bar", $user->getLastName());
        $json = $user->toJSON();
        $this->assertEquals("Bar", $json['lastName']);
    }

    public function testLDUserAnonymous() {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->anonymous(true)->build();
        $this->assertEquals(true, $user->getAnonymous());
        $json = $user->toJSON();
        $this->assertEquals(true, $json['anonymous']);

    }
}

