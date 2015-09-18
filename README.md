LaunchDarkly SDK for PHP
===========================

[![Code Climate](https://codeclimate.com/github/launchdarkly/php-client/badges/gpa.svg)](https://codeclimate.com/github/launchdarkly/php-client)

[![Circle CI](https://circleci.com/gh/launchdarkly/php-client.svg?style=svg)](https://circleci.com/gh/launchdarkly/php-client)

Quick setup
-----------

0. Install the PHP SDK with [Composer](https://getcomposer.org/)

        php composer.phar require launchdarkly/launchdarkly-php

1. After installing, require Composer's autoloader:

		require 'vendor/autoload.php';

2. Create a new LDClient with your API key:

        $client = new LaunchDarkly\LDClient("your_api_key");

Your first feature flag
-----------------------

1. Create a new feature flag on your [dashboard](https://app.launchdarkly.com)

2. In your application code, use the feature's key to check whether the flag is on for each user:

        $user = new LaunchDarkly\LDUser("user@test.com");
        if ($client->toggle("your.flag.key", $user)) {
            # application code to show the feature
        } else {
            # the code to run if the feature is off
        }

Learn more
-----------

Check out our [documentation](http://docs.launchdarkly.com) for in-depth instructions on configuring and using LaunchDarkly. You can also head straight to the [complete reference guide for this SDK](http://docs.launchdarkly.com/v1.0/docs/php-sdk-reference).

Contributing
------------

We encourage pull-requests and other contributions from the community. We've also published an [SDK contributor's guide](http://docs.launchdarkly.com/v1.0/docs/sdk-contributors-guide) that provides a detailed explanation of how our SDKs work.
