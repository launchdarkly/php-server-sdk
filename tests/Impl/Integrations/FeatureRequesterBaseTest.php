<?php

declare(strict_types=1);

namespace LaunchDarkly\Tests\Impl\Integrations;

use LaunchDarkly\Impl\Integrations\FeatureRequesterBase;
use PHPUnit\Framework\TestCase;

class FakeStoreFeatureRequester extends FeatureRequesterBase
{
    /** @var array<string, array<string, string>> */
    private array $data;

    /**
     * @param array<string, array<string, string>> $data map of namespace to (key to raw JSON string)
     */
    public function __construct(array $data)
    {
        parent::__construct('', '', []);
        $this->data = $data;
    }

    protected function readItemString(string $namespace, string $key): ?string
    {
        return $this->data[$namespace][$key] ?? null;
    }

    protected function readItemStringList(string $namespace): ?array
    {
        return array_values($this->data[$namespace] ?? []);
    }
}

class FeatureRequesterBaseTest extends TestCase
{
    private const FLAG_JSON = '{"key":"flagkey","version":1,"on":true,"prerequisites":[],"salt":"salty",' .
        '"targets":[],"contextTargets":[],"rules":[],"fallthrough":{"variation":0},"offVariation":1,' .
        '"variations":["fall","off"],"deleted":false,"trackEvents":false,"trackEventsFallthrough":false,' .
        '"debugEventsUntilDate":null,"clientSide":false}';

    private const SEGMENT_JSON = '{"key":"segkey","version":1,"included":[],"excluded":[],"rules":[],' .
        '"salt":"salty","deleted":false}';

    private const FULL_FLAG_TOMBSTONE_JSON = '{"key":"deletedkey","version":2,"on":false,"prerequisites":[],' .
        '"salt":"","targets":[],"contextTargets":[],"rules":[],"fallthrough":{},"offVariation":null,' .
        '"variations":[],"deleted":true,"trackEvents":false,"trackEventsFallthrough":false,' .
        '"debugEventsUntilDate":null,"clientSide":false}';

    private const MINIMAL_TOMBSTONE_JSON = '{"version":2,"deleted":true}';

    private const KEYED_MINIMAL_TOMBSTONE_JSON = '{"key":"deletedkey","version":2,"deleted":true}';

    public function testGetFeatureReturnsFlag(): void
    {
        $requester = new FakeStoreFeatureRequester(['features' => ['flagkey' => self::FLAG_JSON]]);
        $flag = $requester->getFeature('flagkey');
        $this->assertNotNull($flag);
        $this->assertEquals('flagkey', $flag->getKey());
    }

    public function testGetFeatureReturnsNullForFullSchemaTombstone(): void
    {
        $requester = new FakeStoreFeatureRequester(
            ['features' => ['deletedkey' => self::FULL_FLAG_TOMBSTONE_JSON]]
        );
        $this->assertNull($requester->getFeature('deletedkey'));
    }

    public function testGetFeatureReturnsNullForMinimalTombstone(): void
    {
        $requester = new FakeStoreFeatureRequester(
            ['features' => ['deletedkey' => self::MINIMAL_TOMBSTONE_JSON]]
        );
        $this->assertNull($requester->getFeature('deletedkey'));
    }

    public function testGetFeatureReturnsNullForKeyedMinimalTombstone(): void
    {
        $requester = new FakeStoreFeatureRequester(
            ['features' => ['deletedkey' => self::KEYED_MINIMAL_TOMBSTONE_JSON]]
        );
        $this->assertNull($requester->getFeature('deletedkey'));
    }

    public function testGetSegmentReturnsSegment(): void
    {
        $requester = new FakeStoreFeatureRequester(['segments' => ['segkey' => self::SEGMENT_JSON]]);
        $segment = $requester->getSegment('segkey');
        $this->assertNotNull($segment);
        $this->assertEquals('segkey', $segment->getKey());
    }

    public function testGetSegmentReturnsNullForMinimalTombstone(): void
    {
        $requester = new FakeStoreFeatureRequester(
            ['segments' => ['segkey' => self::MINIMAL_TOMBSTONE_JSON]]
        );
        $this->assertNull($requester->getSegment('segkey'));
    }

    public function testGetAllFeaturesSkipsTombstones(): void
    {
        $requester = new FakeStoreFeatureRequester(['features' => [
            'flagkey' => self::FLAG_JSON,
            'deleted1' => self::FULL_FLAG_TOMBSTONE_JSON,
            'deleted2' => self::MINIMAL_TOMBSTONE_JSON,
            'deleted3' => self::KEYED_MINIMAL_TOMBSTONE_JSON,
        ]]);
        $flags = $requester->getAllFeatures();
        $this->assertNotNull($flags);
        $this->assertEquals(['flagkey'], array_keys($flags));
    }
}
