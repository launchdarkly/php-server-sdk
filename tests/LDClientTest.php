<?php
namespace LaunchDarkly\Tests;

use InvalidArgumentException;
use LaunchDarkly\FeatureFlag;
use LaunchDarkly\FeatureRequester;
use LaunchDarkly\LDClient;
use LaunchDarkly\LDUser;
use LaunchDarkly\LDUserBuilder;
use Psr\Log\LoggerInterface;

class LDClientTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultCtor()
    {
        $this->assertInstanceOf(LDClient::class, new LDClient("BOGUS_SDK_KEY"));
    }

    public function testVariationReturnsFlagValue()
    {
        $flagJson = array(
            'key' => 'feature',
            'version' => 100,
            'deleted' => false,
            'on' => false,
            'targets' => array(),
            'prerequisites' => array(),
            'rules' => array(),
            'offVariation' => 1,
            'fallthrough' => array('variation' => 0),
            'variations' => array('fall', 'off', 'on'),
            'salt' => ''
        );
        $flag = FeatureFlag::decode($flagJson);

        MockFeatureRequester::$flags = array('feature' => $flag);
        $client = new LDClient("someKey", array(
            'feature_requester_class' => MockFeatureRequester::class,
            'events' => false
            ));

        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $value = $client->variation('feature', $user, 'default');
        $this->assertEquals('off', $value);
    }

    public function testVariationReturnsDefaultForUnknownFlag()
    {
        MockFeatureRequester::$flags = array();
        $client = new LDClient("someKey", array(
            'feature_requester_class' => MockFeatureRequester::class,
            'events' => false
            ));

        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $this->assertEquals('argdef', $client->variation('foo', $user, 'argdef'));
    }

    public function testVariationReturnsDefaultFromConfigurationForUnknownFlag()
    {
        MockFeatureRequester::$flags = array();
        $client = new LDClient("someKey", array(
            'feature_requester_class' => MockFeatureRequester::class,
            'events' => false,
            'defaults' => array('foo' => 'fromarray')
        ));

        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $this->assertEquals('fromarray', $client->variation('foo', $user, 'argdef'));
    }

    public function testVariationSendsEvent()
    {
        MockFeatureRequester::$flags = array();
        $client = new LDClient("someKey", array(
            'feature_requester_class' => MockFeatureRequester::class,
            'events' => true
        ));

        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $client->variation('foo', $user, 'argdef');
        $proc = $this->getPrivateField($client, '_eventProcessor');
        $queue = $this->getPrivateField($proc, '_queue');
        $this->assertEquals(1, sizeof($queue));
    }

    public function testAllFlagsReturnsFlagValues()
    {
        $flagJson = array(
            'key' => 'feature',
            'version' => 100,
            'deleted' => false,
            'on' => false,
            'targets' => array(),
            'prerequisites' => array(),
            'rules' => array(),
            'offVariation' => 1,
            'fallthrough' => array('variation' => 0),
            'variations' => array('fall', 'off', 'on'),
            'salt' => ''
        );
        $flag = FeatureFlag::decode($flagJson);

        MockFeatureRequester::$flags = array('feature' => $flag);
        $client = new LDClient("someKey", array(
            'feature_requester_class' => MockFeatureRequester::class,
            'events' => false
            ));

        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $values = $client->allFlags($user);

        $this->assertEquals(array('feature' => 'off'), $values);
    }

    public function testAllFlagsStateReturnsState()
    {
        $flagJson = array(
            'key' => 'feature',
            'version' => 100,
            'deleted' => false,
            'on' => false,
            'targets' => array(),
            'prerequisites' => array(),
            'rules' => array(),
            'offVariation' => 1,
            'fallthrough' => array('variation' => 0),
            'variations' => array('fall', 'off', 'on'),
            'salt' => '',
            'trackEvents' => true,
            'debugEventsUntilDate' => 1000
        );
        $flag = FeatureFlag::decode($flagJson);

        MockFeatureRequester::$flags = array('feature' => $flag);
        $client = new LDClient("someKey", array(
            'feature_requester_class' => MockFeatureRequester::class,
            'events' => false
            ));

        $builder = new LDUserBuilder(3);
        $user = $builder->build();
        $state = $client->allFlagsState($user);
         
        $this->assertTrue($state->isValid());
        $this->assertEquals(array('feature' => 'off'), $state->toValuesMap());
        $expectedState = array(
            'feature' => 'off',
            '$flagsState' => array(
                'feature' => array(
                    'variation' => 1,
                    'version' => 100,
                    'trackEvents' => true,
                    'debugEventsUntilDate' => 1000
                )
            ),
            '$valid' => true
        );
        $this->assertEquals($expectedState, $state->jsonSerialize());
    }

    public function testOnlyValidFeatureRequester()
    {
        $this->setExpectedException(InvalidArgumentException::class);
        new LDClient("BOGUS_SDK_KEY", ['feature_requester_class' => \stdClass::class]);
    }

    public function testSecureModeHash()
    {
        $client = new LDClient("secret", ['offline' => true]);
        $user = new LDUser("Message");
        $this->assertEquals("aa747c502a898200f9e4fa21bac68136f886a0e27aec70ba06daf2e2a5cb5597", $client->secureModeHash($user));
    }
    
    public function testLoggerInterfaceWarn()
    {
        // Use LoggerInterface impl, instead of concreate Logger from Monolog, to demonstrate the problem with `warn`.
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        
        $logger->expects(self::atLeastOnce())->method('warning');
        
        $client = new LDClient('secret', [
            'logger' => $logger,
        ]);
    
        $user = new LDUser('');
        
        $client->variation('MyFeature', $user);
    }
    
    private function getPrivateField(&$object, $fieldName)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $field = $reflection->getProperty($fieldName);
        $field->setAccessible(true);
    
        return $field->getValue($object);
    }
}
