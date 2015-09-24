<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\LDClient;

class LDClientTest extends \PHPUnit_Framework_TestCase {

    public function testDefaultCtor() {
        new LDClient("BOGUS_API_KEY");
    }
}

