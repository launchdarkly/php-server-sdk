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

        $this->assertEquals('test-flag', $flag->build(0)['key']);
        $this->assertEquals(true, $flag->build(0)['on']);

    }

    public function testMakesCopy()
    {
        $td = new TestData();
        $flag = $td->flag('test-flag');

        $flag->variations([1,2,3]);

        $this->assertEquals([1,2,3], $flag->build(0)['variations']);

        $flagCopy = $flag->copy();

        $flagCopy->variations([4,5,6]);

        $this->assertEquals([1,2,3], $flag->build(0)['variations']);
        $this->assertEquals([4,5,6], $flagCopy->build(0)['variations']);

    }

    public function testSetsVariations()
    {
        $td = new TestData();
        $flag = $td->flag('new-flag')->variations('red', 'green', 'blue');
        $this->assertEquals(['red', 'green', 'blue'], $flag->build(0)['variations']);

        $flag2 = $td->flag('new-flag-2')->variations(['red', 'green', 'blue']);
        $this->assertEquals(['red', 'green', 'blue'], $flag2->build(0)['variations']);

        $flag3 = $td->flag('new-flag-3')->variations(['red', 'green', 'blue'], ['cat', 'dog', 'fish']);
        $this->assertEquals([['red', 'green', 'blue'], ['cat', 'dog', 'fish']], $flag3->build(0)['variations']);

        $flag4 = $td->flag('new-flag-4')->variations([['red', 'green', 'blue']]);
        $this->assertEquals(['red', 'green', 'blue'], $flag4->build(0)['variations'][0]);
    }
}
