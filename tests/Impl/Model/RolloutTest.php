<?php

namespace LaunchDarkly\Tests\Impl\Model;

use LaunchDarkly\Impl\Model\Rollout;
use PHPUnit\Framework\TestCase;

class RolloutTest extends TestCase
{
    public function testRolloutPropertiesAreSet()
    {
        $kind = 'experiment';
        $seed = 357;
        
        $rollout = [
            'variations' => [
                ['variation' => 1, 'weight' => 50000],
                ['variation' => 2, 'weight' => 50000]
            ],
            'kind' => $kind,
            'seed' => $seed
        ];
        $decodedRollout = call_user_func(Rollout::getDecoder(), $rollout);
        
        $this->assertEquals(count($decodedRollout->getVariations()), 2);
        $this->assertEquals($decodedRollout->isExperiment(), true);
        $this->assertEquals($decodedRollout->getSeed(), $seed);
    }

    public function testRolloutDefaultProperties()
    {
        $rollout = [
            'variations' => [
                ['variation' => 1, 'weight' => 50000],
                ['variation' => 2, 'weight' => 50000]
            ]
        ];
        $decodedRollout = call_user_func(Rollout::getDecoder(), $rollout);
        
        $this->assertEquals(count($decodedRollout->getVariations()), 2);
        $this->assertEquals($decodedRollout->isExperiment(), false);
        $this->assertEquals($decodedRollout->getSeed(), null);
    }
}
