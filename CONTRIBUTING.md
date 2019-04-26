# Contributing to the LaunchDarkly Server-Side SDK for PHP

LaunchDarkly has published an [SDK contributor's guide](https://docs.launchdarkly.com/docs/sdk-contributors-guide) that provides a detailed explanation of how our SDKs work. See below for additional information on how to contribute to this SDK.

## Submitting bug reports and feature requests
 
The LaunchDarkly SDK team monitors the [issue tracker](https://github.com/launchdarkly/php-server-sdk/issues) in the SDK repository. Bug reports and feature requests specific to this SDK should be filed in this issue tracker. The SDK team will respond to all newly filed issues within two business days.

## Submitting pull requests
 
We encourage pull requests and other contributions from the community. Before submitting pull requests, ensure that all temporary or unintended code is removed. Don't worry about adding reviewers to the pull request; the LaunchDarkly SDK team will add themselves. The SDK team will acknowledge all pull requests within two business days.

## Build instructions

### Prerequisites

The project uses [Composer](https://getcomposer.org/).

### Installing dependencies

From the project root directory:

```
composer install
```

### Testing

To run all unit tests:

```
phpunit
```

By default, the full unit test suite includes live tests of the integrations for Consul, DynamoDB, and Redis. Those tests expect you to have instances of all of those databases running locally. To skip them, set the environment variable `LD_SKIP_DATABASE_TESTS=1` before running the tests.

It is preferable to run tests against all supported minor versions of PHP (as described in `README.md` under Requirements), or at least the lowest and highest versions, prior to submitting a pull request. However, LaunchDarkly's CI tests will run automatically against all supported versions.
