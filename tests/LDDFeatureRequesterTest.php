<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\FeatureFlag;
use LaunchDarkly\LDDFeatureRequester;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class LDDFeatureRequesterTest extends \PHPUnit_Framework_TestCase
{
    /** @var ClientInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $predisClient;
    
    /** @var LoggerInterface */
    private $logger;
    
    protected function setUp()
    {
        parent::setUp();
        
        $this->logger = new NullLogger();
        
        $this->predisClient = $this->getMockBuilder('Predis\ClientInterface');
        $this->predisClient = $this->predisClient->setMethods(array('hget'))
            ->getMockForAbstractClass();
    }
    
    public function testGet()
    {
        $sut = new LDDFeatureRequester('example.com', 'MySdkKey', array(
            'logger' => $this->logger,
            'predis_client' => $this->predisClient,
        ));
        
        $this->predisClient->method('hget')->with('launchdarkly:features', 'foo')
            ->willReturn(json_encode(array(
                'key' => 'foo',
                'version' => 14,
                'on' => true,
                'prerequisites' => array(),
                'salt' => 'c3lzb3BzLXRlc3Q=',
                'sel' => '8ed13de1bfb14507ba7e6dde01f3e035',
                'targets' => array(
                    array(
                        'values' => array(),
                        'variation' => 0,
                    ),
                    array(
                        'values' => array(),
                        'variation' => 1,
                    ),
                ),
                'rules' => array(),
                'fallthrough' => array(
                    'variation' => 0,
                ),
                'offVariation' => null,
                'variations' => array(
                    true,
                    false,
                ),
                'deleted' => false,
            )));
        
        $featureFlag = $sut->get('foo');
        
        self::assertInstanceOf('LaunchDarkly\FeatureFlag', $featureFlag);
        self::assertTrue($featureFlag->isOn());
    }
}
