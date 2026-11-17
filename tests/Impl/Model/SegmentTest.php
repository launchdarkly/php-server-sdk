<?php

namespace LaunchDarkly\Tests\Impl\Model;

use LaunchDarkly\Impl\Model\Segment;

use PHPUnit\Framework\TestCase;

class SegmentTest extends TestCase
{
    public function testDecodeSegmentWithoutOptionalProperties()
    {
        $json = '{"key": "segment", "version": 1,
            "rules": [{"clauses": [{"attribute": "key", "op": "in", "values": ["rule-key"]}]}]}';

        // Decode a segment that omits every optional property.
        $segment = Segment::decode(json_decode($json, true));

        // Each omitted property takes its default value.
        self::assertSame('', $segment->getSalt());
        self::assertSame([], $segment->getIncluded());
        self::assertSame([], $segment->getExcluded());
        self::assertSame([], $segment->getIncludedContexts());
        self::assertSame([], $segment->getExcludedContexts());
        self::assertFalse($segment->isDeleted());
        self::assertFalse($segment->getUnbounded());
        self::assertNull($segment->getGeneration());
        self::assertNull($segment->getRules()[0]->getWeight());
        self::assertFalse($segment->getRules()[0]->getClauses()[0]->isNegate());
    }

    public function testDecodeSegmentWithNullOptionalProperties()
    {
        $json = '{"key": "segment", "version": 1, "salt": null, "deleted": null,
            "included": null, "excluded": null, "includedContexts": null, "excludedContexts": null,
            "unbounded": null, "unboundedContextKind": null, "generation": null,
            "rules": [{"id": null, "weight": null, "bucketBy": null, "rolloutContextKind": null,
                "clauses": [{"contextKind": null, "attribute": "key", "op": "in", "values": ["rule-key"],
                    "negate": null}]}]}';

        // Decode a segment whose optional properties are all null.
        $segment = Segment::decode(json_decode($json, true));

        // Each null property takes its default value.
        self::assertSame('', $segment->getSalt());
        self::assertFalse($segment->isDeleted());
        self::assertSame([], $segment->getIncluded());
        self::assertSame([], $segment->getExcluded());
        self::assertSame([], $segment->getIncludedContexts());
        self::assertSame([], $segment->getExcludedContexts());
        self::assertFalse($segment->getUnbounded());
        self::assertNull($segment->getGeneration());
        self::assertNull($segment->getRules()[0]->getWeight());
        self::assertNull($segment->getRules()[0]->getBucketBy());
        self::assertFalse($segment->getRules()[0]->getClauses()[0]->isNegate());
    }
}
