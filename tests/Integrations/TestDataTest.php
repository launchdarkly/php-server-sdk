<?php

namespace LaunchDarkly\Tests\Integrations;

use PHPUnit\Framework\TestCase;
use LaunchDarkly\Integrations\TestData;

class TestDataTest extends TestCase
{
    public function testMakesFlag()
    {
        $td = new TestData();
        $flag = $td->flag('test-flag');

        $this->assertEquals('test-flag', $flag->_key);
        $this->assertEquals(true, $flag->_on);

    }

    public function testMakesCopy()
    {
        $td = new TestData();
        $flag = $td->flag('test-flag');

        $flag->_variations = [1,2,3];

        $this->assertEquals([1,2,3], $flag->_variations);

        $flagCopy = $flag->copy();

        $flagCopy->_variations = [4,5,6];

        $this->assertEquals([1,2,3], $flag->_variations);
        $this->assertEquals([4,5,6], $flagCopy->_variations);

    }

}
