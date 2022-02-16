<?php

namespace LaunchDarkly\Tests\Impl\Model;

use LaunchDarkly\Impl\Model\WeightedVariation;
use PHPUnit\Framework\TestCase;

class WeightedVariationTest extends TestCase
{
    public function testWeightedVariationPropertiesAreSet()
    {
        $variationId = 2;
        $weight = 35700;
        $untracked = false;
        
        $variation = [
            'variation' => $variationId,
            'weight' => $weight,
            'untracked' => $untracked
        ];
        $decodedVariation = call_user_func(WeightedVariation::getDecoder(), $variation);
        
        $this->assertEquals($decodedVariation->getVariation(), $variationId);
        $this->assertEquals($decodedVariation->getWeight(), $weight);
        $this->assertEquals($decodedVariation->isUntracked(), $untracked);
    }

    public function testWeightedVariationUntrackedDefault()
    {
        $variationId = 2;
        $weight = 35700;
        
        $variation = [
            'variation' => $variationId,
            'weight' => $weight
        ];
        $decodedVariation = call_user_func(WeightedVariation::getDecoder(), $variation);
        
        $this->assertEquals($decodedVariation->getVariation(), $variationId);
        $this->assertEquals($decodedVariation->getWeight(), $weight);
        $this->assertEquals($decodedVariation->isUntracked(), false);
    }
}
