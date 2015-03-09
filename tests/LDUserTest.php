<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\LDUserBuilder;
use LaunchDarkly\LDUser;

class LDUserTest extends \PHPUnit_Framework_TestCase {

    public function testLDUserKey() {
      $user = (new LDUserBuilder("foo@bar.com"))->build();
      $this->assertEquals("foo@bar.com", $user->getKey());
      $this->assertEquals("foo@bar.com", $user->toJSON()['key']);
    }

    public function testLDUserSecondary() {
      $user = (new LDUserBuilder("foo@bar.com"))->secondary("secondary")->build();
      $this->assertEquals("secondary", $user->getSecondary());
      $this->assertEquals("secondary", $user->toJSON()['secondary']);
    }

    public function testLDUserIP() {
      $user = (new LDUserBuilder("foo@bar.com"))->ip("127.0.0.1")->build();
      $this->assertEquals("127.0.0.1", $user->getIP());
      $this->assertEquals("127.0.0.1", $user->toJSON()['ip']);
    }

    public function testLDUserCountry() {
      $user = (new LDUserBuilder("foo@bar.com"))->country("US")->build();
      $this->assertEquals("US", $user->getCountry());
      $this->assertEquals("US", $user->toJSON()['country']);
    }

    public function testLDUserEmail() {
      $user = (new LDUserBuilder("foo@bar.com"))->email("foo+test@bar.com")->build();
      $this->assertEquals("foo+test@bar.com", $user->getEmail());
      $this->assertEquals("foo+test@bar.com", $user->toJSON()['email']);
    }

    public function testLDUserName() {
      $user = (new LDUserBuilder("foo@bar.com"))->name("Foo Bar")->build();
      $this->assertEquals("Foo Bar", $user->getName());
      $this->assertEquals("Foo Bar", $user->toJSON()['name']);
    }    

    public function testLDUserAvatar() {
      $user = (new LDUserBuilder("foo@bar.com"))->avatar("http://www.gravatar.com/avatar/1")->build();
      $this->assertEquals("http://www.gravatar.com/avatar/1", $user->getAvatar());
      $this->assertEquals("http://www.gravatar.com/avatar/1", $user->toJSON()['avatar']);
    }    

    public function testLDUserFirstName() {
      $user = (new LDUserBuilder("foo@bar.com"))->firstName("Foo")->build();
      $this->assertEquals("Foo", $user->getFirstName());
      $this->assertEquals("Foo", $user->toJSON()['firstName']);
    }  

    public function testLDUserLastName() {
      $user = (new LDUserBuilder("foo@bar.com"))->lastName("Bar")->build();
      $this->assertEquals("Bar", $user->getLastName());
      $this->assertEquals("Bar", $user->toJSON()['lastName']);
    }  

    public function testLDUserAnonymous() {
      $user = (new LDUserBuilder("foo@bar.com"))->anonymous(true)->build();
      $this->assertEquals(true, $user->getAnonymous());
      $this->assertEquals(true, $user->toJSON()['anonymous']);

    }
}

