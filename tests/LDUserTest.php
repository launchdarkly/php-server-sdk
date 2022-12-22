<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\LDUser;
use LaunchDarkly\LDUserBuilder;

class LDUserTest extends \PHPUnit\Framework\TestCase
{
    public function testLDUserKey()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->build();
        $this->assertEquals("foo@bar.com", $user->getKey());
    }

    public function testCoerceLDUserKey()
    {
        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $this->assertEquals("string", gettype($user->getKey()));
    }

    public function testEmptyCustom()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        
        $user = $builder->build();
        
        $this->assertInstanceOf(LDUser::class, $user);
    }

    public function testLDUserIP()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->ip("127.0.0.1")->build();
        $this->assertEquals("127.0.0.1", $user->getIP());
    }

    public function testLDUserPrivateIP()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->privateIp("127.0.0.1")->build();
        $this->assertEquals("127.0.0.1", $user->getIP());
        $this->assertEquals(["ip"], $user->getPrivateAttributeNames());
    }

    public function testLDUserCountry()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->country("US")->build();
        $this->assertEquals("US", $user->getCountry());
    }

    public function testLDUserPrivateCountry()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->privateCountry("US")->build();
        $this->assertEquals("US", $user->getCountry());
        $this->assertEquals(["country"], $user->getPrivateAttributeNames());
    }

    public function testLDUserEmail()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->email("foo+test@bar.com")->build();
        $this->assertEquals("foo+test@bar.com", $user->getEmail());
    }

    public function testLDUserPrivateEmail()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->privateEmail("foo+test@bar.com")->build();
        $this->assertEquals("foo+test@bar.com", $user->getEmail());
        $this->assertEquals(["email"], $user->getPrivateAttributeNames());
    }

    public function testLDUserName()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->name("Foo Bar")->build();
        $this->assertEquals("Foo Bar", $user->getName());
    }

    public function testLDUserPrivateName()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->privateName("Foo Bar")->build();
        $this->assertEquals("Foo Bar", $user->getName());
        $this->assertEquals(["name"], $user->getPrivateAttributeNames());
    }

    public function testLDUserAvatar()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->avatar("http://www.gravatar.com/avatar/1")->build();
        $this->assertEquals("http://www.gravatar.com/avatar/1", $user->getAvatar());
    }

    public function testLDUserPrivateAvatar()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->privateAvatar("http://www.gravatar.com/avatar/1")->build();
        $this->assertEquals("http://www.gravatar.com/avatar/1", $user->getAvatar());
        $this->assertEquals(["avatar"], $user->getPrivateAttributeNames());
    }

    public function testLDUserFirstName()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->firstName("Foo")->build();
        $this->assertEquals("Foo", $user->getFirstName());
    }

    public function testLDUserPrivateFirstName()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->privateFirstName("Foo")->build();
        $this->assertEquals("Foo", $user->getFirstName());
        $this->assertEquals(["firstName"], $user->getPrivateAttributeNames());
    }

    public function testLDUserLastName()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->lastName("Bar")->build();
        $this->assertEquals("Bar", $user->getLastName());
    }

    public function testLDUserPrivateLastName()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->privateLastName("Bar")->build();
        $this->assertEquals("Bar", $user->getLastName());
        $this->assertEquals(["lastName"], $user->getPrivateAttributeNames());
    }

    public function testLDUserCustom()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->customAttribute("foo", "bar")->customAttribute("baz", "boo")->build();
        $this->assertEquals(["foo" => "bar", "baz" => "boo"], $user->getCustom());
    }

    public function testLDUserPrivateCustom()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->privateCustomAttribute("foo", "bar")->privateCustomAttribute("baz", "boo")->build();
        $this->assertEquals(["foo" => "bar", "baz" => "boo"], $user->getCustom());
        $this->assertEquals(["foo", "baz"], $user->getPrivateAttributeNames());
    }

    public function testLDUserAnonymous()
    {
        $builder = new LDUserBuilder("foo@bar.com");
        $user = $builder->anonymous(true)->build();
        $this->assertEquals(true, $user->getAnonymous());
    }

    public function testLDUserBlankKey()
    {
        $builder = new LDUserBuilder("");
        $user = $builder->build();
        $this->assertTrue($user->isKeyBlank());
        $this->assertFalse(is_null($user->getKey()));

        $builder = new LDUserBuilder("key");
        $user = $builder->build();
        $this->assertFalse($user->isKeyBlank());
    }

    public function testLDUserNullKey()
    {
        $this->expectException(\TypeError::class);
        $builder = new LDUserBuilder(null);
    }
}
