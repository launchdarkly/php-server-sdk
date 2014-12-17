LaunchDarkly SDK for PHP
===========================

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
        if ($client->getFlag("your.flag.key", $user)) {
            # application code to show the feature
        } else {
            # the code to run if the feature is off
        }