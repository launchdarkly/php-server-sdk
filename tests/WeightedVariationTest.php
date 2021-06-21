<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\WeightedVariation;

class WeightedVariationTest extends \PHPUnit_Framework_TestCase
{
    public function testWeightedVariationPropertiesAreSet()
    {
        $variationId = 2;
        $weight = 35700;
        $untracked = false;
        
        $variation = array(
            'variation' => $variationId,
            'weight' => $weight,
            'untracked' => $untracked
        );
        $decodedVariation = call_user_func(WeightedVariation::getDecoder(), $variation);
        
        $this->assertEquals($decodedVariation->getVariation(), $variationId);
        $this->assertEquals($decodedVariation->getWeight(), $weight);
        $this->assertEquals($decodedVariation->isUntracked(), $untracked);
    }

    public function testWeightedVariationUntrackedDefault()
    {
        $variationId = 2;
        $weight = 35700;
        
        $variation = array(
            'variation' => $variationId,
            'weight' => $weight
        );
        $decodedVariation = call_user_func(WeightedVariation::getDecoder(), $variation);
        
        $this->assertEquals($decodedVariation->getVariation(), $variationId);
        $this->assertEquals($decodedVariation->getWeight(), $weight);
        $this->assertEquals($decodedVariation->isUntracked(), false);
    }
}
