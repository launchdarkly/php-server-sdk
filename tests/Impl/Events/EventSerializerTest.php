<?php

namespace LaunchDarkly\Tests\Impl\Events;

use LaunchDarkly\Impl\Events\EventSerializer;
use LaunchDarkly\LDContext;
use PHPUnit\Framework\TestCase;

class EventSerializerTest extends TestCase
{
    private function getContext(): LDContext
    {
        return LDContext::builder('abc')
            ->set('bizzle', 'def')
            ->set('dizzle', 'ghi')
            ->set('firstName', 'Sue')
            ->build();
    }

    private function getContextSpecifyingOwnPrivateAttr()
    {
        return LDContext::builder('abc')
            ->set('bizzle', 'def')
            ->set('dizzle', 'ghi')
            ->set('firstName', 'Sue')
            ->private('dizzle')
            ->build();
    }

    private function getFullContextResult()
    {
        return [
            'kind' => 'user',
            'key' => 'abc',
            'firstName' => 'Sue',
            'bizzle' => 'def',
            'dizzle' => 'ghi'
        ];
    }

    private function getContextResultWithAllAttrsHidden()
    {
        return [
            'kind' => 'user',
            'key' => 'abc',
            '_meta' => [
                'redactedAttributes' => ['bizzle', 'dizzle', 'firstName']
            ]
        ];
    }

    private function getContextResultWithSomeAttrsHidden()
    {
        return [
            'kind' => 'user',
            'key' => 'abc',
            'dizzle' => 'ghi',
            '_meta' => [
                'redactedAttributes' => ['bizzle', 'firstName']
            ]
        ];
    }

    private function getContextResultWithOwnSpecifiedAttrHidden()
    {
        return [
            'kind' => 'user',
            'key' => 'abc',
            'firstName' => 'Sue',
            'bizzle' => 'def',
            '_meta' => [
                'redactedAttributes' => ['dizzle']
            ]
        ];
    }

    private function makeEvent($context)
    {
        return [
            'creationDate' => 1000000,
            'key' => 'abc',
            'kind' => 'identify',
            'context' => $context
        ];
    }

    private function getJsonForContextBySerializingEvent($user)
    {
        $es = new EventSerializer([]);
        $event = $this->makeEvent($user);
        return json_decode($es->serializeEvents([$event]), true)[0]['context'];
    }

    public function testAllContextAttrsSerialized()
    {
        $es = new EventSerializer([]);
        $event = $this->makeEvent($this->getContext());
        $json = $es->serializeEvents([$event]);
        $expected = $this->makeEvent($this->getFullContextResult());
        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testAllContextAttrsPrivate()
    {
        $es = new EventSerializer(['all_attributes_private' => true]);
        $event = $this->makeEvent($this->getContext());
        $json = $es->serializeEvents([$event]);
        $expected = $this->makeEvent($this->getContextResultWithAllAttrsHidden());
        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testRedactsAllAttributesFromAnonymousContextWithFeatureEvent()
    {
        $anonymousContext = LDContext::builder('abc')
            ->anonymous(true)
            ->set('bizzle', 'def')
            ->set('dizzle', 'ghi')
            ->set('firstName', 'Sue')
            ->build();

        $es = new EventSerializer([]);
        $event = $this->makeEvent($anonymousContext);
        $event['kind'] = 'feature';
        $json = $es->serializeEvents([$event]);

        // But we redact all attributes when the context is anonymous
        $expectedContextOutput = $this->getContextResultWithAllAttrsHidden();
        $expectedContextOutput['anonymous'] = true;

        $expected = $this->makeEvent($expectedContextOutput);
        $expected['kind'] = 'feature';

        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testRedactsAllAttributesFromAnonymousContextWithMigrationOpEvent()
    {
        $anonymousContext = LDContext::builder('abc')
            ->anonymous(true)
            ->set('bizzle', 'def')
            ->set('dizzle', 'ghi')
            ->set('firstName', 'Sue')
            ->build();

        $es = new EventSerializer([]);
        $event = $this->makeEvent($anonymousContext);
        $event['kind'] = 'migration_op';
        $json = $es->serializeEvents([$event]);

        // Migration op events inline the full context, so anonymous attributes must be redacted.
        $expectedContextOutput = $this->getContextResultWithAllAttrsHidden();
        $expectedContextOutput['anonymous'] = true;

        $expected = $this->makeEvent($expectedContextOutput);
        $expected['kind'] = 'migration_op';

        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testRedactsAllAttributesFromAnonymousContextWithCustomEvent()
    {
        $anonymousContext = LDContext::builder('abc')
            ->anonymous(true)
            ->set('bizzle', 'def')
            ->set('dizzle', 'ghi')
            ->set('firstName', 'Sue')
            ->build();

        $es = new EventSerializer([]);
        $event = $this->makeEvent($anonymousContext);
        $event['kind'] = 'custom';
        $json = $es->serializeEvents([$event]);

        // Custom events inline the full context, so anonymous attributes must be redacted.
        $expectedContextOutput = $this->getContextResultWithAllAttrsHidden();
        $expectedContextOutput['anonymous'] = true;

        $expected = $this->makeEvent($expectedContextOutput);
        $expected['kind'] = 'custom';

        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testDoesNotRedactAttributesFromAnonymousContextWithNonFeatureEvent()
    {
        $anonymousContext = LDContext::builder('abc')
            ->anonymous(true)
            ->set('bizzle', 'def')
            ->set('dizzle', 'ghi')
            ->set('firstName', 'Sue')
            ->build();

        $es = new EventSerializer([]);
        $event = $this->makeEvent($anonymousContext);
        $json = $es->serializeEvents([$event]);

        // But we redact all attributes when the context is anonymous
        $expectedContextOutput = $this->getFullContextResult();
        $expectedContextOutput['anonymous'] = true;

        $expected = $this->makeEvent($expectedContextOutput);

        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testRedactsAllAttributesOnlyIfContextIsAnonymous()
    {
        $userContext = LDContext::builder('user-key')
            ->kind('user')
            ->anonymous(true)
            ->name('Example user')
            ->build();

        $orgContext = LDContext::builder('org-key')
            ->kind('org')
            ->anonymous(false)
            ->name('Example org')
            ->build();

        $multiContext = LDContext::createMulti($userContext, $orgContext);

        $es = new EventSerializer([]);
        $event = $this->makeEvent($multiContext);
        $event['kind'] = 'feature';
        $json = $es->serializeEvents([$event]);

        $expectedContextOutput = [
            'kind' => 'multi',
            'user' => [
                'key' => 'user-key',
                'anonymous' => true,
                '_meta' => ['redactedAttributes' => ['name']]
            ],
            'org' => [
                'key' => 'org-key',
                'name' => 'Example org',
            ],
        ];
        $expected = $this->makeEvent($expectedContextOutput);
        $expected['kind'] = 'feature';

        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testSomeContextAttrsPrivate()
    {
        $es = new EventSerializer(['private_attribute_names' => ['firstName', 'bizzle']]);
        $event = $this->makeEvent($this->getContext());
        $json = $es->serializeEvents([$event]);
        $expected = $this->makeEvent($this->getContextResultWithSomeAttrsHidden());
        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testPerContextPrivateAttr()
    {
        $es = new EventSerializer([]);
        $event = $this->makeEvent($this->getContextSpecifyingOwnPrivateAttr());
        $json = $es->serializeEvents([$event]);
        $expected = $this->makeEvent($this->getContextResultWithOwnSpecifiedAttrHidden());
        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testPerContextPrivateAttrPlusGlobalPrivateAttrs()
    {
        $es = new EventSerializer(['private_attribute_names' => ['firstName', 'bizzle']]);
        $event = $this->makeEvent($this->getContextSpecifyingOwnPrivateAttr());
        $json = $es->serializeEvents([$event]);
        $expected = $this->makeEvent($this->getContextResultWithAllAttrsHidden());
        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testObjectPropertyRedaction()
    {
        $es = new EventSerializer(['private_attribute_names' => ['/b/prop1', '/c/prop2/sub1']]);
        $context = LDContext::builder('user-key')
            ->name('a')
            ->set('b', ['prop1' => true, 'prop2' => 3])
            ->set('c', ['prop1' => ['sub1' => true], 'prop2' => ['sub1' => 4, 'sub2' => 5]])
            ->build();
        $json = $es->serializeEvents([$this->makeEvent($context)]);
        $expected = $this->makeEvent([
            'kind' => 'user',
            'key' => 'user-key',
            'name' => 'a',
            'b' => ['prop2' => 3],
            'c' => ['prop1' => ['sub1' => true], 'prop2' => ['sub2' => 5]],
            '_meta' => [
                'redactedAttributes' => ['/b/prop1', '/c/prop2/sub1']
            ]
        ]);
        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testObjectPropertyRedactionInContextParsedFromJsonString()
    {
        $es = new EventSerializer([]);
        $context = LDContext::fromJson(json_encode([
            'kind' => 'user',
            'key' => 'user-key',
            'address' => ['street' => 'secret', 'city' => 'x'],
            '_meta' => ['privateAttributes' => ['/address/street']],
        ]));
        $json = $es->serializeEvents([$this->makeEvent($context)]);
        $expected = $this->makeEvent([
            'kind' => 'user',
            'key' => 'user-key',
            'address' => ['city' => 'x'],
            '_meta' => [
                'redactedAttributes' => ['/address/street']
            ]
        ]);
        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testObjectPropertyRedactionWithStdClassValues()
    {
        $es = new EventSerializer(['private_attribute_names' => ['/b/prop1', '/c/prop2/sub1', '/d/0']]);
        $context = LDContext::builder('user-key')
            ->set('b', (object)['prop1' => true, 'prop2' => 3])
            ->set('c', (object)['prop1' => (object)['sub1' => true], 'prop2' => (object)['sub1' => 4, 'sub2' => 5]])
            ->set('d', (object)['0' => 'secret', '1' => 'visible'])
            ->build();
        $json = $es->serializeEvents([$this->makeEvent($context)]);
        $expected = $this->makeEvent([
            'kind' => 'user',
            'key' => 'user-key',
            'b' => ['prop2' => 3],
            'c' => ['prop1' => ['sub1' => true], 'prop2' => ['sub2' => 5]],
            'd' => ['1' => 'visible'],
            '_meta' => [
                'redactedAttributes' => ['/b/prop1', '/c/prop2/sub1', '/d/0']
            ]
        ]);
        $this->assertEquals([$expected], json_decode($json, true));
        // The redacted object with a numeric property name must remain a JSON object, not an array.
        $this->assertStringContainsString('"d":{"1":"visible"}', $json);
    }

    public function testRedactingEveryPropertyOfStdClassValueLeavesEmptyObject()
    {
        $es = new EventSerializer(['private_attribute_names' => ['/b/prop1']]);
        $context = LDContext::builder('user-key')
            ->set('b', (object)['prop1' => true])
            ->build();
        $json = $es->serializeEvents([$this->makeEvent($context)]);
        $this->assertStringContainsString('"b":{}', $json);
        $this->assertSame(['/b/prop1'], json_decode($json, true)[0]['context']['_meta']['redactedAttributes']);
    }

    public function testObjectPropertyRedactionForContextParsedFromJsonString()
    {
        $es = new EventSerializer(['private_attribute_names' => ['/c/prop2/sub1']]);
        $context = LDContext::fromJson(json_encode([
            'kind' => 'user',
            'key' => 'user-key',
            'b' => ['prop1' => true, 'prop2' => 3],
            'c' => ['prop1' => ['sub1' => true], 'prop2' => ['sub1' => 4, 'sub2' => 5]],
            '_meta' => ['privateAttributes' => ['/b/prop1']],
        ]));
        $json = $es->serializeEvents([$this->makeEvent($context)]);
        $expected = $this->makeEvent([
            'kind' => 'user',
            'key' => 'user-key',
            'b' => ['prop2' => 3],
            'c' => ['prop1' => ['sub1' => true], 'prop2' => ['sub2' => 5]],
            '_meta' => [
                'redactedAttributes' => ['/b/prop1', '/c/prop2/sub1']
            ]
        ]);
        $this->assertEquals([$expected], json_decode($json, true));
    }

    public function testObjectPropertyRedactionForStdClassValue()
    {
        $context = LDContext::builder('user-key')
            ->set('b', (object)['prop1' => true, 'prop2' => (object)['sub1' => 4, 'sub2' => 5]])
            ->private('/b/prop1', '/b/prop2/sub1')
            ->build();
        $json = $this->getJsonForContextBySerializingEvent($context);
        $this->assertEquals(['prop2' => ['sub2' => 5]], $json['b']);
        $this->assertEquals(['/b/prop1', '/b/prop2/sub1'], $json['_meta']['redactedAttributes']);
    }

    public function testStdClassWithNumericPropertyNamesStaysAnObject()
    {
        $context = LDContext::fromJson('{"kind": "user", "key": "user-key", "b": {"0": "x", "1": "y", "secret": "z"}, '
            . '"c": {"0": "x", "1": "y"}, "_meta": {"privateAttributes": ["/b/secret", "/c/1"]}}');
        $es = new EventSerializer([]);
        $json = $es->serializeEvents([$this->makeEvent($context)]);
        $this->assertStringContainsString('"b":{"0":"x","1":"y"}', $json);
        $this->assertStringContainsString('"c":{"0":"x"}', $json);
    }

    public function testStdClassWithAllPropertiesRedactedIsAnEmptyObject()
    {
        $context = LDContext::fromJson('{"kind": "user", "key": "user-key", "b": {"secret": "z"}, "c": {}, '
            . '"_meta": {"privateAttributes": ["/b/secret"]}}');
        $es = new EventSerializer([]);
        $json = $es->serializeEvents([$this->makeEvent($context)]);
        $this->assertStringContainsString('"b":{}', $json);
        $this->assertStringContainsString('"c":{}', $json);
    }

    public function testObjectPropertyRedactionForMultiKindContextParsedFromJsonString()
    {
        $context = LDContext::fromJson('{"kind": "multi", '
            . '"user": {"key": "u", "b": {"secret": 1, "public": 2}, "_meta": {"privateAttributes": ["/b/secret"]}}, '
            . '"org": {"key": "o", "b": {"secret": 3, "public": 4}}}');
        $json = $this->getJsonForContextBySerializingEvent($context);
        $this->assertEquals(['public' => 2], $json['user']['b']);
        $this->assertEquals(['/b/secret'], $json['user']['_meta']['redactedAttributes']);
        $this->assertEquals(['secret' => 3, 'public' => 4], $json['org']['b']);
    }

    public function testObjectPropertyRedactionForMixedObjectsAndArrays()
    {
        $context = LDContext::builder('user-key')
            ->set('b', ['x' => (object)['y' => ['secret' => 1, 'public' => 2]]])
            ->private('/b/x/y/secret')
            ->build();
        $json = $this->getJsonForContextBySerializingEvent($context);
        $this->assertEquals(['x' => ['y' => ['public' => 2]]], $json['b']);
        $this->assertEquals(['/b/x/y/secret'], $json['_meta']['redactedAttributes']);
    }

    public function testObjectPropertyRedactionWithEscapedPathComponents()
    {
        $context = LDContext::fromJson('{"kind": "user", "key": "user-key", "b": {"x/y": 1, "x~y": 2, "public": 3}, '
            . '"_meta": {"privateAttributes": ["/b/x~1y", "/b/x~0y"]}}');
        $json = $this->getJsonForContextBySerializingEvent($context);
        $this->assertEquals(['public' => 3], $json['b']);
        $this->assertEquals(['/b/x~1y', '/b/x~0y'], $json['_meta']['redactedAttributes']);
    }

    public function testObjectsInsideListAreNotRedactedOrChanged()
    {
        // Attribute references do not index into lists, so a list of objects passes through as-is.
        $context = LDContext::fromJson('{"kind": "user", "key": "user-key", "b": [{"secret": 1}, {"secret": 2}], '
            . '"_meta": {"privateAttributes": ["/b/0/secret"]}}');
        $es = new EventSerializer([]);
        $json = $es->serializeEvents([$this->makeEvent($context)]);
        $this->assertStringContainsString('"b":[{"secret":1},{"secret":2}]', $json);
        $this->assertStringNotContainsString('redactedAttributes', $json);
    }

    public function testObjectPrivatePathThatDoesNotMatchLeavesValueUnchanged()
    {
        $context = LDContext::fromJson('{"kind": "user", "key": "user-key", "b": {"secret": "s", "public": 1}, '
            . '"_meta": {"privateAttributes": ["/b/secret/deeper", "/b/missing"]}}');
        $json = $this->getJsonForContextBySerializingEvent($context);
        $this->assertEquals(['secret' => 's', 'public' => 1], $json['b']);
        $this->assertArrayNotHasKey('_meta', $json);
    }

    public function testWholeObjectAttributeIsRedacted()
    {
        $context = LDContext::fromJson('{"kind": "user", "key": "user-key", "b": {"secret": "s"}, '
            . '"_meta": {"privateAttributes": ["b"]}}');
        $json = $this->getJsonForContextBySerializingEvent($context);
        $this->assertArrayNotHasKey('b', $json);
        $this->assertEquals(['b'], $json['_meta']['redactedAttributes']);
    }

    public function testObjectRedactionDoesNotChangeContext()
    {
        $context = LDContext::fromJson('{"kind": "user", "key": "user-key", "b": {"c": {"secret": "s", "public": 1}}, '
            . '"_meta": {"privateAttributes": ["/b/c/secret"]}}');
        $before = json_encode($context->get('b'));
        $this->getJsonForContextBySerializingEvent($context);
        $this->assertSame($before, json_encode($context->get('b')));
        $this->assertSame('s', $context->get('b')->c->secret);
    }

    public function testArrayWithNumericKeyBeforeRedactedKey()
    {
        $context = LDContext::builder('user-key')
            ->set('b', ['123' => 'a', 'secret' => 's'])
            ->private('/b/secret')
            ->build();
        $es = new EventSerializer([]);
        $json = $es->serializeEvents([$this->makeEvent($context)]);
        $this->assertStringContainsString('"b":{"123":"a"}', $json);
        $this->assertStringContainsString('"redactedAttributes":["\/b\/secret"]', $json);
    }

    public function testArrayWithNumericKeyAfterRedactedKeyStaysAnObject()
    {
        // After redaction only key 0 is left. That array is a list, but the value is a JSON object.
        $context = LDContext::builder('user-key')
            ->set('b', ['secret' => 's', 0 => 'a'])
            ->private('/b/secret')
            ->build();
        $es = new EventSerializer([]);
        $json = $es->serializeEvents([$this->makeEvent($context)]);
        $this->assertStringContainsString('"b":{"0":"a"}', $json);
        $this->assertStringContainsString('"redactedAttributes":["\/b\/secret"]', $json);
    }

    public function testArrayRedactionOfNumericKeys()
    {
        $context = LDContext::builder('user-key')
            ->set('b', ['123' => 'a', '456' => 'b'])
            ->set('c', [1 => 'x', 0 => 'y'])
            ->private('/b/123', '/c/0')
            ->build();
        $es = new EventSerializer([]);
        $json = $es->serializeEvents([$this->makeEvent($context)]);
        $this->assertStringContainsString('"b":{"456":"b"}', $json);
        $this->assertStringContainsString('"c":{"1":"x"}', $json);
        $this->assertStringContainsString('"redactedAttributes":["\/b\/123","\/c\/0"]', $json);
    }

    public function testArrayAndJsonStringInputRedactTheSame()
    {
        $json = '{"kind": "user", "key": "user-key", "ids": {"7": {"secret": "s", "public": 1}}, '
            . '"_meta": {"privateAttributes": ["/ids/7/secret"]}}';
        $fromString = $this->getJsonForContextBySerializingEvent(LDContext::fromJson($json));
        $fromArray = $this->getJsonForContextBySerializingEvent(LDContext::fromJson(json_decode($json, true)));
        $this->assertEquals(['7' => ['public' => 1]], $fromArray['ids']);
        $this->assertEquals(['/ids/7/secret'], $fromArray['_meta']['redactedAttributes']);
        $this->assertEquals($fromString, $fromArray);
    }

    public function testStdClassInsideArrayWithNumericKey()
    {
        $context = LDContext::builder('user-key')
            ->set('b', ['0' => 'x', 'o' => (object)['secret' => 's', 'public' => 1]])
            ->private('/b/o/secret')
            ->build();
        $json = $this->getJsonForContextBySerializingEvent($context);
        $this->assertEquals(['0' => 'x', 'o' => ['public' => 1]], $json['b']);
        $this->assertEquals(['/b/o/secret'], $json['_meta']['redactedAttributes']);
    }

    public function testListArrayIsNotChanged()
    {
        $context = LDContext::builder('user-key')
            ->set('b', [['secret' => 's'], ['secret' => 't']])
            ->private('/b/0/secret', '/b/0')
            ->build();
        $es = new EventSerializer([]);
        $json = $es->serializeEvents([$this->makeEvent($context)]);
        $this->assertStringContainsString('"b":[{"secret":"s"},{"secret":"t"}]', $json);
        $this->assertStringNotContainsString('redactedAttributes', $json);
    }

    public function testStdClassWithCycleProducesNoOutput()
    {
        // json_encode fails on a cycle. The serializer must not recurse forever before that.
        $o = new \stdClass();
        $o->secret = 's';
        $o->self = $o;
        $context = LDContext::builder('user-key')->set('b', $o)->private('/b/secret')->build();
        $es = new EventSerializer([]);
        $this->assertSame('', $es->serializeEvents([$this->makeEvent($context)]));
    }

    public function testObjectPropertyRedactionAtDeepNesting()
    {
        $value = (object)['secret' => 's', 'public' => 1];
        $path = '/b';
        for ($i = 0; $i < 100; $i++) {
            $value = (object)['n' => $value];
            $path .= '/n';
        }
        $context = LDContext::builder('user-key')->set('b', $value)->private($path . '/secret')->build();
        $json = $this->getJsonForContextBySerializingEvent($context);
        $inner = $json['b'];
        for ($i = 0; $i < 100; $i++) {
            $inner = $inner['n'];
        }
        $this->assertEquals(['public' => 1], $inner);
        $this->assertEquals([$path . '/secret'], $json['_meta']['redactedAttributes']);
    }

    public function testValueNestedBeyondJsonDepthLimitProducesNoOutput()
    {
        // Redaction stops at the json_encode depth limit. Then json_encode fails, so the private
        // value is not sent.
        $value = (object)['secret' => 's'];
        $path = '/b';
        for ($i = 0; $i < 600; $i++) {
            $value = (object)['n' => $value];
            $path .= '/n';
        }
        $context = LDContext::builder('user-key')->set('b', $value)->private($path . '/secret')->build();
        $es = new EventSerializer([]);
        $this->assertSame('', $es->serializeEvents([$this->makeEvent($context)]));
    }

    public function testContextKey()
    {
        $context = LDContext::create("foo@bar.com");
        $json = $this->getJsonForContextBySerializingEvent($context);
        $this->assertSame("foo@bar.com", $json['key']);
    }
}
