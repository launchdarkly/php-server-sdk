<?php

namespace LaunchDarkly\Tests\Integrations;

use PHPUnit\Framework\TestCase;
use LaunchDarkly\Integrations\TestData;

class TestDataTest extends TestCase
{
    public function initializesWithEmptyData()
    {

    }

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

    public function testFlagBuilderCanSetFallthroughVariation()
    {
        $td = new TestData();
        $flag = $td->flag('test-flag');
        $flag->fallthroughVariation(2);

        $this->assertEquals(['variation' => 2], $flag->build(0)['fallthrough']);
    }

    public function testFlagConfigSimpleBoolean()
    {
        $td = new TestData();

        $flag = $td->flag('test-flag-1');
        $flagBuild = $flag->build(0);
        $this->assertEquals(true, $flagBuild['on']);
        $this->assertEquals(['variation' => 0], $flagBuild['fallthrough']);

        $flagBool = $td->flag('test-flag-2')->booleanFlag();
        $flagBoolBuild = $flagBool->build(0);
        $this->assertEquals(true, $flagBoolBuild['on']);
        $this->assertEquals(['variation' => 0], $flagBoolBuild['fallthrough']);

        $flagOn = $td->flag('test-flag-3')->on(true);
        $flagOnBuild = $flagOn->build(0);
        $this->assertEquals(true, $flagOnBuild['on']);
        $this->assertEquals(['variation' => 0], $flagOnBuild['fallthrough']);

        $flagOff = $td->flag('test-flag-4')->on(false);
        $flagOffBuild = $flagOff->build(0);
        $this->assertEquals(false, $flagOffBuild['on']);
        $this->assertEquals(['variation' => 0], $flagOffBuild['fallthrough']);

        $flagFallthroughAndOffVariation = $td->flag('test-flag-5')
                                             ->fallthroughVariation(true)
                                             ->offVariation(false);
        $flagFallthroughAndOffVariationBuild = $flagFallthroughAndOffVariation->build(0);
        $this->assertEquals(true, $flagFallthroughAndOffVariationBuild['on']);
        $this->assertEquals(['variation' => 0], $flagFallthroughAndOffVariationBuild['fallthrough']);
    }

    public function testFlagBuilderClearTargets() 
    {
        $td = new TestData();
        $flag = $td->flag('test-flag')
                    // TODO: Fill the flag with targets
                   ->clearTargets()
                   ->build(0);
        $this->assertEquals([], $flag['targets']);
    }

    public function testFlagBuilderBooleanConfigMethodsForcesFlagToBeBoolean()
    {
        $td = new TestData();
        $overwriteBoolFlag1 = $td->flag('test-flag')->variations(1, 2)->booleanFlag()->build(0);
        $this->assertEquals([true, false], $overwriteBoolFlag1['variations']);
        $this->assertEquals(true, $overwriteBoolFlag1['on']);
        $this->assertEquals(1, $overwriteBoolFlag1['offVariation']);
        $this->assertEquals(['variation' => 0], $overwriteBoolFlag1['fallthrough']);

        $overwriteBoolFlag2 = $td->flag('test-flag')->variations(true, 2)->booleanFlag()->build(0);
        $this->assertEquals([true, false], $overwriteBoolFlag2['variations']);
        $this->assertEquals(true, $overwriteBoolFlag2['on']);
        $this->assertEquals(1, $overwriteBoolFlag2['offVariation']);
        $this->assertEquals(['variation' => 0], $overwriteBoolFlag2['fallthrough']);

        $boolFlag = $td->flag('test-flag')->booleanFlag()->build(0);
        $this->assertEquals([true, false], $boolFlag['variations']);
        $this->assertEquals(true, $boolFlag['on']);
        $this->assertEquals(1, $boolFlag['offVariation']);
        $this->assertEquals(['variation' => 0], $boolFlag['fallthrough']);
    }

    public function testFlagConfigStringVariations()
    {
        $td = new TestData();
        $stringVariationFlag = $td->flag('test-flag')
                                    ->variations('red', 'green', 'blue')
                                    ->offVariation(0)
                                    ->fallthroughVariation(2)
                                    ->build(0);
        $this->assertEquals(['red', 'green', 'blue'], $stringVariationFlag['variations']);
        $this->assertEquals(true, $stringVariationFlag['on']);
        $this->assertEquals(0, $stringVariationFlag['offVariation']);
        $this->assertEquals(['variation' => 2], $stringVariationFlag['fallthrough']);
    }

    public function testFlagBuilderDefaultsToBooleanFlag()
    {
        $td = new TestData();
        $flag = $td->flag('empty-flag');
        $this->assertEquals([true, false], $flag->build(0)['variations']);
        $this->assertEquals(['variation' => 0], $flag->build(0)['fallthrough']);
        $this->assertEquals(1, $flag->build(0)['offVariation']);
    }

    public function testFlagbuilderCanTurnFlagOff()
    {
        $td = new TestData();
        $flag = $td->flag('test-flag');
        $flag->on(false);

        $this->assertEquals(false, $flag->build(0)['on']);
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
