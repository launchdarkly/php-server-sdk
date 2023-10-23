<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\LDContext;
use LaunchDarkly\Types\AttributeReference;

class LDContextTest extends \PHPUnit\Framework\TestCase
{
    public function testCreateWithDefaultKind()
    {
        $c = LDContext::create('a');

        self::assertContextValid($c);
        self::assertFalse($c->isMultiple());
        self::assertEquals('a', $c->getKey());
        self::assertEquals('user', $c->getKind());
        self::assertNull($c->getName());
        self::assertFalse($c->isAnonymous());
        self::assertEquals([], $c->getCustomAttributeNames());
        self::assertSame(['user' => 'a'], $c->getKeys());
    }

    public function testCreateWithNonDefaultKind()
    {
        $c = LDContext::create('a', 'b');

        self::assertContextValid($c);
        self::assertFalse($c->isMultiple());
        self::assertEquals('a', $c->getKey());
        self::assertEquals('b', $c->getKind());
        self::assertNull($c->getName());
        self::assertFalse($c->isAnonymous());
        self::assertEquals([], $c->getCustomAttributeNames());
        self::assertSame(['b' => 'a'], $c->getKeys());
    }

    public function testBuilderWithDefaultKind()
    {
        $c = LDContext::builder('a')->build();

        self::assertContextValid($c);
        self::assertFalse($c->isMultiple());
        self::assertEquals('a', $c->getKey());
        self::assertEquals('user', $c->getKind());
        self::assertNull($c->getName());
        self::assertFalse($c->isAnonymous());
        self::assertEquals([], $c->getCustomAttributeNames());
        self::assertSame(['user' => 'a'], $c->getKeys());
    }

    public function testBuilderWithNonDefaultKind()
    {
        $c = LDContext::builder('a')->kind('b')->build();

        self::assertContextValid($c);
        self::assertFalse($c->isMultiple());
        self::assertEquals('a', $c->getKey());
        self::assertEquals('b', $c->getKind());
        self::assertNull($c->getName());
        self::assertFalse($c->isAnonymous());
        self::assertEquals([], $c->getCustomAttributeNames());
        self::assertSame(['b' => 'a'], $c->getKeys());
    }

    public function testBuilderName()
    {
        $c = LDContext::builder('a')->name('b')->build();

        self::assertContextValid($c);
        self::assertEquals('a', $c->getKey());
        self::assertEquals('b', $c->getName());
    }

    public function testBuilderAnonymous()
    {
        $c = LDContext::builder('a')->anonymous(true)->build();

        self::assertContextValid($c);
        self::assertEquals('a', $c->getKey());
        self::assertTrue($c->isAnonymous());
    }

    public function testBuilderSetCustomAttributes()
    {
        $c = LDContext::builder('a')
            ->set('b', true)
            ->set('c', 'd')
            ->build();

        self::assertContextValid($c);
        self::assertEquals('a', $c->getKey());
        self::assertEquals(true, $c->get('b'));
        self::assertEquals('d', $c->get('c'));
        self::assertEquals(['b', 'c'], $c->getCustomAttributeNames());
    }

    public function testBuilderSetBuiltInAttributeByName()
    {
        $c = LDContext::builder('')
            ->set('key', 'a')
            ->set('kind', 'b')
            ->set('name', 'c')
            ->set('anonymous', true)
            ->build();

        self::assertContextValid($c);
        self::assertEquals('a', $c->getKey());
        self::assertEquals('b', $c->getKind());
        self::assertEquals('c', $c->getName());
        self::assertTrue($c->isAnonymous());

        // name is nullable
        $c1 = LDContext::builder('a')->name('c')->name(null)->build();
        self::assertNull($c1->getName());
    }

    public function testBuilderSetBuiltInAttributeByNameTypeChecking()
    {
        $b = LDContext::builder('a')->kind('b')->name('c')->anonymous(true);

        $b->set('key', null);
        $b->set('key', 3);
        self::assertFalse($b->trySet('key', null));
        self::assertFalse($b->trySet('key', 3));
        self::assertEquals('a', $b->build()->getKey());

        $b->set('kind', null);
        $b->set('kind', 3);
        self::assertFalse($b->trySet('kind', null));
        self::assertFalse($b->trySet('kind', 3));
        self::assertEquals('b', $b->build()->getKind());

        $b->set('name', 3);
        self::assertFalse($b->trySet('name', 3));
        self::assertEquals('c', $b->build()->getName());

        $b->set('anonymous', null);
        $b->set('anonymous', 3);
        self::assertFalse($b->trySet('anonymous', null));
        self::assertFalse($b->trySet('anonymous', 3));
        self::assertTrue($b->build()->isAnonymous());
    }

    public function testGetBuiltInAttributeByName()
    {
        $c = LDContext::builder('a')->kind('b')->name('c')->anonymous(true)->build();
        self::assertEquals('a', $c->get('key'));
        self::assertEquals('b', $c->get('kind'));
        self::assertEquals('c', $c->get('name'));
        self::assertTrue($c->get('anonymous'));
    }

    public function testGetUnknownAttribute()
    {
        $c = LDContext::create('a');
        self::assertNull($c->get('b'));
    }

    public function testPrivateAttributes()
    {
        self::assertNull(LDContext::create('a')->getPrivateAttributes());

        $c = LDContext::builder('a')->private('b', '/c/d')->private(AttributeReference::fromPath('e'))->build();
        self::assertEquals(
            [
                AttributeReference::fromPath('b'),
                AttributeReference::fromPath('/c/d'),
                AttributeReference::fromPath('e')
            ],
            $c->getPrivateAttributes()
        );
    }

    public function testCreateMulti()
    {
        $c1 = LDContext::create('a', 'kind1');
        $c2 = LDContext::create('b', 'kind2');
        $mc = LDContext::createMulti($c1, $c2);

        self::assertContextValid($mc);
        self::assertTrue($mc->isMultiple());
        self::assertEquals(2, $mc->getIndividualContextCount());

        self::assertSame($c1, $mc->getIndividualContext(0));
        self::assertSame($c2, $mc->getIndividualContext(1));
        self::assertNull($mc->getIndividualContext(-1));
        self::assertNull($mc->getIndividualContext(2));

        self::assertSame($c1, $mc->getIndividualContext('kind1'));
        self::assertSame($c2, $mc->getIndividualContext('kind2'));
        self::assertNull($mc->getIndividualContext('kind3'));
        self::assertSame(['kind1' => 'a', 'kind2' => 'b'], $mc->getKeys());
    }

    public function testMultiBuilder()
    {
        $c1 = LDContext::create('a', 'kind1');
        $c2 = LDContext::create('b', 'kind2');
        self::assertEquals(
            LDContext::createMulti($c1, $c2),
            LDContext::multiBuilder()->add($c1)->add($c2)->build()
        );
    }

    public function testCreateMultiFlattensNestedMultiContext()
    {
        $c1 = LDContext::create('a', 'kind1');
        $c2 = LDContext::create('b', 'kind2');
        $c3 = LDContext::create('c', 'kind3');
        $c2Plus3 = LDContext::createMulti($c2, $c3);
        self::assertEquals(
            LDContext::createMulti($c1, $c2, $c3),
            LDContext::createMulti($c1, $c2Plus3)
        );
    }

    public function testMultiBuilderFlattensNestedMultiContext()
    {
        $c1 = LDContext::create('a', 'kind1');
        $c2 = LDContext::create('b', 'kind2');
        $c3 = LDContext::create('c', 'kind3');
        $c2Plus3 = LDContext::createMulti($c2, $c3);
        self::assertEquals(
            LDContext::createMulti($c1, $c2, $c3),
            LDContext::multiBuilder()->add($c1)->add($c2Plus3)->build()
        );
    }

    public function testFullyQualifiedKey()
    {
        self::assertEquals('key1', LDContext::create('key1')->getFullyQualifiedKey());
        self::assertEquals('kind1:key1', LDContext::create('key1', 'kind1')->getFullyQualifiedKey());
        self::assertEquals(
            'kind1:key1:kind2:key2',
            LDContext::createMulti(
                LDContext::create('key2', 'kind2'),
                LDContext::create('key1', 'kind1')
            )->getFullyQualifiedKey()
        );
    }

    public function testEquals()
    {
        self::assertContextsFromFactoryEqual(fn () => LDContext::create('a'));
        self::assertContextsFromFactoryEqual(fn () => LDContext::create('a', 'kind1'));
        self::assertContextsFromFactoryEqual(fn () => LDContext::builder('a')->name('b')->build());
        self::assertContextsFromFactoryEqual(fn () => LDContext::builder('a')->anonymous(true)->build());
        self::assertContextsFromFactoryEqual(fn () => LDContext::builder('a')->set('b', true)->set('c', 3)->build());
        self::assertContextsEqual(
            LDContext::builder('a')->set('b', true)->set('c', 3)->build(),
            LDContext::builder('a')->set('c', 3)->set('b', true)->build()
        );
        self::assertContextsFromFactoryEqual(fn () => LDContext::create('invalid', 'kind'));

        self::assertContextsUnequal(LDContext::create('a', 'kind1'), LDContext::create('a', 'kind2'));
        self::assertContextsUnequal(LDContext::create('b', 'kind1'), LDContext::create('a', 'kind1'));
        self::assertContextsUnequal(
            LDContext::builder('a')->name('b')->build(),
            LDContext::builder('a')->name('c')->build()
        );
        self::assertContextsUnequal(
            LDContext::builder('a')->anonymous(true)->build(),
            LDContext::builder('a')->build()
        );
        self::assertContextsUnequal(
            LDContext::builder('a')->set('b', true)->build(),
            LDContext::builder('a')->set('b', false)->build()
        );
        self::assertContextsUnequal(
            LDContext::builder('a')->set('b', true)->build(),
            LDContext::builder('a')->set('b', true)->set('c', false)->build()
        );

        self::assertContextsFromFactoryEqual(
            fn () => LDContext::createMulti(LDContext::create('a', 'kind1'), LDContext::create('b', 'kind2'))
        );
        self::assertContextsEqual(
            LDContext::createMulti(LDContext::create('a', 'kind1'), LDContext::create('b', 'kind2')),
            LDContext::createMulti(LDContext::create('b', 'kind2'), LDContext::create('a', 'kind1'))
        );

        self::assertContextsUnequal(
            LDContext::createMulti(LDContext::create('a', 'kind1'), LDContext::create('b', 'kind2')),
            LDContext::createMulti(LDContext::create('a', 'kind1'), LDContext::create('c', 'kind2'))
        );
        self::assertContextsUnequal(
            LDContext::createMulti(LDContext::create('a', 'kind1'), LDContext::create('b', 'kind2')),
            LDContext::createMulti(LDContext::create('a', 'kind1'))
        );

        self::assertContextsUnequal(LDContext::create('invalid', 'kind'), LDContext::createMulti());
    }

    public function testJsonEncoding()
    {
        self::assertJsonStringEqualsJsonString(
            '{"kind": "kind1", "key": "a"}',
            json_encode(LDContext::create('a', 'kind1'))
        );
        self::assertJsonStringEqualsJsonString(
            '{"kind": "kind1", "key": "a", "name": "b"}',
            json_encode(LDContext::builder('a')->kind('kind1')->name('b')->build())
        );
        self::assertJsonStringEqualsJsonString(
            '{"kind": "kind1", "key": "a", "anonymous": true}',
            json_encode(LDContext::builder('a')->kind('kind1')->anonymous(true)->build())
        );
        self::assertJsonStringEqualsJsonString(
            '{"kind": "kind1", "key": "a", "b": true, "c": 3}',
            json_encode(LDContext::builder('a')->kind('kind1')->set('b', true)->set('c', 3)->build())
        );
        self::assertJsonStringEqualsJsonString(
            '{"kind": "kind1", "key": "a", "_meta": {"privateAttributes": ["b"]}}',
            json_encode(LDContext::builder('a')->kind('kind1')->private('b')->build())
        );
        self::assertJsonStringEqualsJsonString(
            '{"kind": "multi", "kind1": {"key": "key1"}, "kind2": {"key": "key2"}}',
            json_encode(LDContext::createMulti(LDContext::create('key1', 'kind1'), LDContext::create('key2', 'kind2')))
        );
    }

    public function testJsonDecoding()
    {
        self::assertContextsEqual(
            LDContext::create('key1', 'kind1'),
            LDContext::fromJson('{"kind": "kind1", "key": "key1"}')
        );
        self::assertContextsEqual(
            LDContext::create('key1', 'kind1'),
            LDContext::fromJson(['kind' => 'kind1', 'key' => 'key1'])
        );
        self::assertContextsEqual(
            LDContext::builder('key1')->kind('kind1')->name('a')->build(),
            LDContext::fromJson('{"kind": "kind1", "key": "key1", "name": "a"}')
        );
        self::assertContextsEqual(
            LDContext::builder('key1')->kind('kind1')->anonymous(true)->build(),
            LDContext::fromJson('{"kind": "kind1", "key": "key1", "anonymous": true}')
        );
        self::assertContextsEqual(
            LDContext::createMulti(LDContext::create('key1', 'kind1'), LDContext::create('key2', 'kind2')),
            LDContext::fromJson('{"kind": "multi", "kind1": {"key": "key1"}, "kind2": {"key": "key2"}}')
        );
    }

    private static function assertContextValid($c)
    {
        self::assertNull($c->getError());
        self::assertTrue($c->isValid());
    }

    private static function assertContextsFromFactoryEqual($factory)
    {
        self::assertContextsEqual($factory(), $factory());
    }

    private static function assertContextsEqual($c1, $c2)
    {
        self::assertTrue($c1->equals($c2), "expected $c1 but got $c2");
    }

    private static function assertContextsUnequal($c1, $c2)
    {
        self::assertFalse($c1->equals($c2), "$c2 should not have been equal to $c1");
    }
}
