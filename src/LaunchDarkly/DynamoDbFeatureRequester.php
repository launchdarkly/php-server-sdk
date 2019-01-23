<?php
namespace LaunchDarkly;

use Aws\DynamoDb\DynamoDbClient;
use Psr\Log\LoggerInterface;

class DynamoDbFeatureRequester implements FeatureRequester
{
    /** @var string */
    protected $_tableName;
    /** @var string */
    protected $_prefix;
    /** @var DynamoDbClient */
    protected $_client;
    /** @var LoggerInterface */
    private $_logger;

    public function __construct($baseUri, $sdkKey, $options)
    {
        if (!isset($options['dynamodb_table'])) {
            throw new \InvalidArgumentException('dynamodb_table must be specified');
        }
        $this->_tableName = $options['dynamodb_table'];

        $dynamoDbOptions = isset($options['dynamodb_options']) ? $options['dynamodb_options'] : array();
        $dynamoDbOptions['version'] = '2012-08-10'; // in the AWS SDK for PHP, this is how you specify the API version
        $this->_client = new DynamoDbClient($dynamoDbOptions);

        $prefix = isset($options['dynamodb_prefix']) ? $options['dynamodb_prefix'] : '';
        $this->_prefix = ($prefix != null && count($prefix) > 0) ? $prefix . '/' : '';

        $this->_logger = $options['logger'];
    }

    /**
     * Gets an individual feature flag.
     *
     * @param $key string feature flag key
     * @return FeatureFlag|null The decoded JSON feature data, or null if missing
     */
    public function getFeature($key)
    {
        $json = $this->getJsonItem('features', $key);
        if (!$json) {
            $this->_logger->warning("DynamoDBFeatureRequester: Attempted to get missing feature with key: " . $key);
            return null;
        }
        $flag = FeatureFlag::decode($json);
        if ($flag) {
            if ($flag->isDeleted()) {
                $this->_logger->warning("DynamoDBFeatureRequester: Attempted to get deleted feature with key: " . $key);
                return null;
            }
            return $flag;
        } else {
            $this->_logger->warning("DynamoDBFeatureRequester: Attempted to get missing feature with key: " . $key);
            return null;
        }
    }

    /**
     * Gets an individual user segment.
     *
     * @param $key string segment key
     * @return Segment|null The decoded JSON segment data, or null if missing
     */
    public function getSegment($key)
    {
        $json = $this->getJsonItem('segments', $key);
        if (!$json) {
            $this->_logger->warning("DynamoDBFeatureRequester: Attempted to get missing segment with key: " . $key);
            return null;
        }
        $segment = Segment::decode($json);
        if ($segment) {
            if ($segment->isDeleted()) {
                $this->_logger->warning("DynamoDBFeatureRequester: Attempted to get deleted segment with key: " . $key);
                return null;
            }
            return $segment;
        } else {
            $this->_logger->warning("DynamoDBFeatureRequester: Attempted to get missing segment with key: " . $key);
            return null;
        }
    }

    /**
     * Gets all features
     *
     * @return array()|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures()
    {
        $jsonItems = $this->queryJsonItems('features');
        $itemsOut = array();
        foreach ($jsonItems as $json) {
            $flag = FeatureFlag::decode($json);
            if ($flag && !$flag->isDeleted()) {
                $itemsOut[$flag->getKey()] = $flag;
            }
        }
        return $itemsOut;
    }

    protected function getJsonItem($namespace, $key)
    {
        $request = array(
            'TableName' => $this->_tableName,
            'ConsistentRead' => true,
            'Key' => array(
                'namespace' => array('S' => $this->_prefix . $namespace),
                'key' => array('S' => $key)
            )
        );
        $result = $this->_client->getItem($request);
        if (!$result) {
            return null;
        }
        $item = $result->get('Item');
        if (!$item || !isset($item['item'])) {
            return null;
        }
        $attr = $item['item'];
        return isset($attr['S']) ? json_decode($attr['S'], true) : null;
    }

    protected function queryJsonItems($namespace)
    {
        $items = array();
        $request = array(
            'TableName' => $this->_tableName,
            'ConsistentRead' => true,
            'KeyConditions' => array(
                'namespace' => array(
                    'ComparisonOperator' => 'EQ',
                    'AttributeValueList' => array(array('S' => $this->_prefix . $namespace))
                )
            )
        );
        // We may need to repeat this query several times due to pagination
        $moreItems = true;
        while ($moreItems) {
            $result = $this->_client->query($request);
            foreach ($result->get('Items') as $item) {
                if (isset($item['item'])) {
                    $attr = $item['item'];
                    if (isset($attr['S'])) {
                        $items[] = json_decode($attr['S'], true);
                    }
                }
            }
            if (isset($result['LastEvaluatedKey']) && $result['LastEvaluatedKey']) {
                $request['ExclusiveStartKey'] = $result['LastEvaluatedKey'];
            } else {
                $moreItems = false;
            }
        }
        return $items;
    }
}
