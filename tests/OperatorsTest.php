<?php

namespace LaunchDarkly\Tests;


use LaunchDarkly\Operators;

class OperatorsTest extends \PHPUnit_Framework_TestCase {

    public function testDefaultCtor() {
        $this->assertEquals(0, Operators::parseTime(0));
        $this->assertEquals(100, Operators::parseTime(100));
        $this->assertEquals(100, Operators::parseTime(100));
        $this->assertEquals(1000, Operators::parseTime("1970-01-01T00:00:01Z"));
        $this->assertEquals(1001, Operators::parseTime("1970-01-01T00:00:01.001Z"));


        $this->assertEquals(null, Operators::parseTime("NOT A REAL TIMESTAMP"));
        $this->assertEquals(null, Operators::parseTime([]));

    }
}