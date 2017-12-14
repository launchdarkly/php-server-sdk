LaunchDarkly SDK for PHP
===========================

[![Code Climate](https://codeclimate.com/github/launchdarkly/php-client/badges/gpa.svg)](https://codeclimate.com/github/launchdarkly/php-client)

[![Circle CI](https://circleci.com/gh/launchdarkly/php-client.svg?style=svg)](https://circleci.com/gh/launchdarkly/php-client)

Requirements
------------
1. PHP 5.3 or higher. 

Quick setup
-----------

1. Install the PHP SDK and monolog for logging with [Composer](https://getcomposer.org/)

        php composer.phar require launchdarkly/launchdarkly-php
        php composer.phar require "guzzlehttp/guzzle:6.2.1"
        php composer.phar require "kevinrob/guzzle-cache-middleware": "1.4.1"

1. After installing, require Composer's autoloader:

		require 'vendor/autoload.php';

1. Create a new LDClient with your SDK key:

        $client = new LaunchDarkly\LDClient("your_sdk_key");

Your first feature flag
-----------------------

1. Create a new feature flag on your [dashboard](https://app.launchdarkly.com)

2. In your application code, use the feature's key to check whether the flag is on for each user:

        $user = new LaunchDarkly\LDUser("user@test.com");
        if ($client->variation("your.flag.key", $user)) {
            # application code to show the feature
        } else {
            # the code to run if the feature is off
        }

Fetching flags
--------------

There are two distinct methods of integrating LaunchDarkly in a PHP environment.  

* [Guzzle Cache Middleware](https://github.com/Kevinrob/guzzle-cache-middleware) to request and cache HTTP responses in an in-memory array (default)
* [ld-relay](https://github.com/launchdarkly/ld-relay) to retrieve and store flags in Redis (recommended)

We strongly recommend using the ld-relay.  Per-flag caching (Guzzle method) is only intended for low-throughput environments.

Using Guzzle
============

Require Guzzle as a dependency:

    php composer.phar require "guzzlehttp/guzzle:6.2.1"
    php composer.phar require "kevinrob/guzzle-cache-middleware:1.4.1"

It will then be used as the default way of fetching flags.

With Guzzle, you could persist your cache somewhere other than the default in-memory store, like Memcached or Redis.  You could then specify your cache when initializing the client with the [cache option](https://github.com/launchdarkly/php-client/blob/master/src/LaunchDarkly/LDClient.php#L44).

    $client = new LaunchDarkly\LDClient("YOUR_SDK_KEY", array("cache" => $cacheStorage));


Using LD-Relay
==============

The LaunchDarkly Relay Proxy ([ld-relay](https://github.com/launchdarkly/ld-relay)) consumes the LaunchDarkly streaming API and can update
a Redis cache operating in your production environment. The ld-relay offers many benefits such as performance and feature flag consistency. With PHP applications, we strongly recommend setting up ld-relay with a Redis store.

1. Set up ld-relay in [daemon-mode](https://github.com/launchdarkly/ld-relay#redis-storage-and-daemon-mode) with Redis

2. Require Predis as a dependency:

        php composer.phar require "predis/predis:1.0.*"

3. Create the LDClient with the Redis feature requester as an option:

        $client = new LaunchDarkly\LDClient("your_sdk_key", [
            'feature_requester_class' => 'LaunchDarkly\LDDFeatureRequester',
            'redis_host' => 'your.redis.host',
            'redis_port' => 6379
        ]);

4. If ld-relay is configured for [event forwarding](https://github.com/launchdarkly/ld-relay#event-forwarding), you can configure the LDClient to publish events to ld-relay instead of directly to `events.launchdarkly.com`. Using `GuzzleEventPublisher` with ld-relay event forwarding can be an efficient alternative to the default `curl`-based event publishing.

        $client = new LaunchDarkly\LDClient("your_sdk_key", [
            'event_publisher_class' => 'LaunchDarkly\GuzzleEventPublisher',
            'events_uri' => 'http://your-ldrelay-host:8030',
            'feature_requester_class' => 'LaunchDarkly\LDDFeatureRequester',
            'redis_host' => 'your.redis.host',
            'redis_port' => 6379
        ]);

Testing
-------

We run integration tests for all our SDKs using a centralized test harness. This approach gives us the ability to test for consistency across SDKs, as well as test networking behavior in a long-running application. These tests cover each method in the SDK, and verify that event sending, flag evaluation, stream reconnection, and other aspects of the SDK all behave correctly.

Learn more
-----------

Check out our [documentation](http://docs.launchdarkly.com) for in-depth instructions on configuring and using LaunchDarkly. You can also head straight to the [complete reference guide for this SDK](http://docs.launchdarkly.com/docs/php-sdk-reference).

Contributing
------------

We encourage pull-requests and other contributions from the community. We've also published an [SDK contributor's guide](http://docs.launchdarkly.com/docs/sdk-contributors-guide) that provides a detailed explanation of how our SDKs work.

About LaunchDarkly
-----------

* LaunchDarkly is a continuous delivery platform that provides feature flags as a service and allows developers to iterate quickly and safely. We allow you to easily flag your features and manage them from the LaunchDarkly dashboard.  With LaunchDarkly, you can:
    * Roll out a new feature to a subset of your users (like a group of users who opt-in to a beta tester group), gathering feedback and bug reports from real-world use cases.
    * Gradually roll out a feature to an increasing percentage of users, and track the effect that the feature has on key metrics (for instance, how likely is a user to complete a purchase if they have feature A versus feature B?).
    * Turn off a feature that you realize is causing performance problems in production, without needing to re-deploy, or even restart the application with a changed configuration file.
    * Grant access to certain features based on user attributes, like payment plan (eg: users on the ‘gold’ plan get access to more features than users in the ‘silver’ plan). Disable parts of your application to facilitate maintenance, without taking everything offline.
* LaunchDarkly provides feature flag SDKs for
    * [Java](http://docs.launchdarkly.com/docs/java-sdk-reference "Java SDK")
    * [JavaScript](http://docs.launchdarkly.com/docs/js-sdk-reference "LaunchDarkly JavaScript SDK")
    * [PHP](http://docs.launchdarkly.com/docs/php-sdk-reference "LaunchDarkly PHP SDK")
    * [Python](http://docs.launchdarkly.com/docs/python-sdk-reference "LaunchDarkly Python SDK")
    * [Python Twisted](http://docs.launchdarkly.com/docs/python-twisted-sdk-reference "LaunchDarkly Python Twisted SDK")
    * [Go](http://docs.launchdarkly.com/docs/go-sdk-reference "LaunchDarkly Go SDK")
    * [Node.JS](http://docs.launchdarkly.com/docs/node-sdk-reference "LaunchDarkly Node SDK")
    * [.NET](http://docs.launchdarkly.com/docs/dotnet-sdk-reference "LaunchDarkly .Net SDK")
    * [Ruby](http://docs.launchdarkly.com/docs/ruby-sdk-reference "LaunchDarkly Ruby SDK")
    * [iOS](http://docs.launchdarkly.com/docs/ios-sdk-reference "LaunchDarkly iOS SDK")
    * [Android](http://docs.launchdarkly.com/docs/android-sdk-reference "LaunchDarkly Android SDK")
* Explore LaunchDarkly
    * [launchdarkly.com](http://www.launchdarkly.com/ "LaunchDarkly Main Website") for more information
    * [docs.launchdarkly.com](http://docs.launchdarkly.com/  "LaunchDarkly Documentation") for our documentation and SDKs
    * [apidocs.launchdarkly.com](http://apidocs.launchdarkly.com/  "LaunchDarkly API Documentation") for our API documentation
    * [blog.launchdarkly.com](http://blog.launchdarkly.com/  "LaunchDarkly Blog Documentation") for the latest product updates
    * [Feature Flagging Guide](https://github.com/launchdarkly/featureflags/  "Feature Flagging Guide") for best practices and strategies
