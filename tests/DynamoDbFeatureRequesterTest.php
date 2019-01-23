<?php

namespace LaunchDarkly\Tests;

use Aws\DynamoDb\DynamoDbClient;
use LaunchDarkly\DynamoDbFeatureRequester;
use Psr\Log\NullLogger;

class DynamoDbFeatureRequesterTest extends FeatureRequesterTestBase
{
    const TABLE_NAME = 'test-table';
    const PREFIX = 'test';

    private static $dynamoDbClient;

    public static function setUpBeforeClass()
    {
        self::$dynamoDbClient = new DynamoDbClient(self::makeDynamoDbOptions());
        self::createTableIfNecessary();
    }

    private static function makeDynamoDbOptions()
    {
        return array(
            'credentials' => array('key' => 'x', 'secret' => 'x'), // credentials for local test instance are arbitrary
            'endpoint' => 'http://localhost:8000',
            'region' => 'us-east-1',
            'version' => '2012-08-10'
        );
    }

    protected function makeRequester()
    {
        $options = array(
            'dynamodb_table' => self::TABLE_NAME,
            'dynamodb_options' => self::makeDynamoDbOptions(),
            'dynamodb_prefix' => self::PREFIX,
            'logger' => new NullLogger()
        );
        return new DynamoDbFeatureRequester('', '', $options);
    }

    protected function putItem($namespace, $key, $version, $json)
    {
        self::$dynamoDbClient->putItem(array(
            'TableName' => self::TABLE_NAME,
            'Item' => array(
                'namespace' => array('S' => self::PREFIX . '/' . $namespace),
                'key' => array('S' => $key),
                'version' => array('N' => strval($version)),
                'item' => array('S' =>  $json)
            )
        ));
    }

    protected function deleteExistingData()
    {
        $result = self::$dynamoDbClient->scan(array(
            'TableName' => self::TABLE_NAME,
            'ConsistentRead' => true,
            'AttributesToGet' => array('namespace', 'key')
        ));
        $requests = array();
        foreach ($result['Items'] as $item) {
            $requests[] = array(
                'DeleteRequest' => array('Key' => $item)
            );
        }
        if (count($requests)) {
            self::$dynamoDbClient->batchWriteItem(array(
                'RequestItems' => array(
                    self::TABLE_NAME => $requests
                )
            ));
        }
    }

    private static function createTableIfNecessary()
    {
        try {
            self::$dynamoDbClient->describeTable(array('TableName' => self::TABLE_NAME));
            return; // table already exists
        } catch (\Exception $e) {
        }
        self::$dynamoDbClient->createTable(array(
            'TableName' => self::TABLE_NAME,
            'AttributeDefinitions' => array(
                array(
                    'AttributeName' => 'namespace',
                    'AttributeType' => 'S'
                ),
                array(
                    'AttributeName' => 'key',
                    'AttributeType' => 'S'
                )
            ),
            'KeySchema' => array(
                array(
                    'AttributeName' => 'namespace',
                    'KeyType' => 'HASH'
                ),
                array(
                    'AttributeName' => 'key',
                    'KeyType' => 'RANGE'
                )
            ),
            'ProvisionedThroughput' => array(
                'ReadCapacityUnits' => 1,
                'WriteCapacityUnits' => 1
            )
        ));
        while (true) { // table may not be available immediately
            try {
                self::$dynamoDbClient->describeTable(array('TableName' => self::TABLE_NAME));
                return;
            } catch (\Exception $e) {
            }
            sleep(1);
        }
    }
}
