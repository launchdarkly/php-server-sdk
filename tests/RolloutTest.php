<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\Rollout;

class RolloutTest extends \PHPUnit_Framework_TestCase
{
    public function testRolloutPropertiesAreSet()
    {
        $kind = 'experiment';
        $seed = 357;
        
        $rollout = array(
            'variations' => array(
                array('variation' => 1, 'weight' => 50000),
                array('variation' => 2, 'weight' => 50000)
            ),
            'kind' => $kind,
            'seed' => $seed
        );
        $decodedRollout = call_user_func(Rollout::getDecoder(), $rollout);
        
        $this->assertEquals(count($decodedRollout->getVariations()), 2);
        $this->assertEquals($decodedRollout->isExperiment(), true);
        $this->assertEquals($decodedRollout->getSeed(), $seed);
    }

    public function testRolloutDefaultProperties()
    {
        $rollout = array(
            'variations' => array(
                array('variation' => 1, 'weight' => 50000),
                array('variation' => 2, 'weight' => 50000)
            )
        );
        $decodedRollout = call_user_func(Rollout::getDecoder(), $rollout);
        
        $this->assertEquals(count($decodedRollout->getVariations()), 2);
        $this->assertEquals($decodedRollout->isExperiment(), false);
        $this->assertEquals($decodedRollout->getSeed(), null);
    }
}
