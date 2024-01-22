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

    public function testContextKey()
    {
        $context = LDContext::create("foo@bar.com");
        $json = $this->getJsonForContextBySerializingEvent($context);
        $this->assertSame("foo@bar.com", $json['key']);
    }
}
