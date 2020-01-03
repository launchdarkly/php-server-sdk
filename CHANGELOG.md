# Change log

All notable changes to the LaunchDarkly PHP SDK will be documented in this file. This project adheres to [Semantic Versioning](http://semver.org).

## [3.7.1] - 2020-01-03
### Fixed:
- Loosened the Monolog dependency constraint so that it will accept either a 1.x or a 2.x version. This should be compatible with all currently supported PHP versions; the SDK's use of Monolog does not rely on any features that are specific to 1.x. (Thanks, [mrtus](https://github.com/launchdarkly/php-server-sdk/pull/132)!)
- In rare circumstances (depending on the exact data in the flag configuration, the flag's salt value, and the user properties), a percentage rollout could fail and return a default value, logging the error "Data inconsistency in feature flag ... variation/rollout object with no variation or rollout". This would happen if the user's hashed value fell exactly at the end of the last "bucket" (the last variation defined in the rollout). This has been fixed so that the user will get the last variation.

## [3.7.0] - 2019-12-13
### Added:
- Added integration with the [`phpredis`](https://github.com/phpredis/phpredis) extension, which supports persistent Redis connections unlike the existing `predis` integration. See `LaunchDarkly::Integrations::PHPRedis`. (Thanks, [nicofff](https://github.com/launchdarkly/php-server-sdk/pull/128)!)

## [3.6.0] - 2019-10-01
### Added:
- Added support for upcoming LaunchDarkly experimentation features. See `LDClient.track`.


## [3.5.5] - 2019-06-05
### Fixed:
- The SDK could throw an exception when calling `allFlagsState()` if APC/APCu caching was enabled. This bug was introduced in the 3.5.0 release. (Thanks, [omnicolor](https://github.com/launchdarkly/php-server-sdk/pull/124)!)
- Improved unit test coverage for the caching logic.

## [3.5.4] - 2019-05-10
### Changed:
- Changed the package name from `launchdarkly/launchdarkly-php` to `launchdarkly/server-sdk`

There are no other changes in this release. Substituting `launchdarkly/launchdarkly-php` version 3.5.3 with `launchdarkly/server-sdk` version 3.5.4 will not affect functionality.

## [3.5.3] - 2019-04-26
### Fixed:
- Segment rollout calculations did not work correctly if the rollout was based on a user attribute other than `key`; all users would end up in the same bucket. (Thanks, [m6w6](https://github.com/launchdarkly/php-server-sdk/pull/121)!)
- Running the SDK unit tests is now simpler, as the database integrations can be skipped. See `CONTRIBUTING.md`.

# Note on future releases

The LaunchDarkly SDK repositories are being renamed for consistency. This repository is now `php-server-sdk` rather than `php-client`.

The package name will also change. In the 3.5.3 release, it is still `launchdarkly/launchdarkly-php`; in all future releases, it will be `launchdarkly/server-sdk`. No further updates to the `launchdarkly/launchdarkly-php` package will be published after this release.

## [3.5.2] - 2019-04-11
### Fixed:
- In the 3.5.1 release, the `VERSION` constant was incorrectly still reporting the version as "3.5.0". The constant is now correct. There are no other changes in this release.


## [3.5.1] - 2019-04-03
### Fixed:
- Setting user attributes to non-string values when a string was expected would cause analytics events not to be processed. The SDK will now convert attribute values to strings as needed.
- If `track` or `identify` is called without a user, the SDK now logs a warning, and does not send an analytics event to LaunchDarkly (since it would not be processed without a user).

## [3.5.0] - 2019-01-30
### Added:
- It is now possible to use Consul or DynamoDB as a data store with `ld-relay`, similar to the existing Redis integration. See `LaunchDarkly\Integrations\Consul` and `LaunchDarkly\Integrations\DynamoDb`, and the reference guide [Using a persistent feature store](https://docs.launchdarkly.com/v2.0/docs/using-a-persistent-feature-store).
- When using the Redis integration, you can specify a Redis connection timeout different from the default of 5 seconds by setting the option `redis_timeout` to the desired number of seconds. (Thanks, [jjozefowicz](https://github.com/launchdarkly/php-client/pull/113)!)
- It is now possible to inject feature flags into the client from local JSON files, replacing the normal LaunchDarkly connection. This would typically be for testing purposes. See `LaunchDarkly\Integrations\Files`.
- The `allFlagsState` method now accepts a new option, `detailsOnlyForTrackedFlags`, which reduces the size of the JSON representation of the flag state by omitting some metadata. Specifically, it omits any data that is normally used for generating detailed evaluation events if a flag does not have event tracking or debugging turned on.

### Changed:
- The `feature_requester` and `event_publisher` configuration options now work differently: you can still set them to an instance of an implementation object, but you can also set them to a class or a class name (i.e. the same as the `feature_requester_class` option), or a factory function. Therefore, the `_class` versions of these options are no longer necessary. However, the old semantics still work, so you can for instance set `event_publisher_class` to `"LaunchDarkly\GuzzleEventPublisher"`, even though the new preferred way is to set `event_publisher` to `LaunchDarkly\Integrations\Guzzle::featureRequester()`.

### Fixed:
- JSON data from `allFlagsState` is now slightly smaller even if you do not use the new option described above, because it omits the flag property for event tracking unless that property is true.
- The `$_anonymous` property of the `LDUser` class was showing up as public rather than protected. (Thanks, [dstockto](https://github.com/launchdarkly/php-client/pull/114)!)

## [3.4.1] - 2018-09-25
### Fixed:
- Improved the performance of `allFlags`/`allFlagsState` by not making redundant individual requests for prerequisite flags, when a flag is being evaluated that has prerequisites. Instead it will reuse the same flag data that it already obtained from LaunchDarkly in the "get all the flags" request.

## [3.4.0] - 2018-09-04
### Added:
- The new `LDClient` method `variationDetail` allows you to evaluate a feature flag (using the same parameters as you would for `variation`) and receive more information about how the value was calculated. This information is returned in an object that contains both the result value and a "reason" object which will tell you, for instance, if the user was individually targeted for the flag or was matched by one of the flag's rules, or if the flag returned the default value due to an error.

### Fixed:
- When evaluating a prerequisite feature flag, the analytics event for the evaluation did not include the result value if the prerequisite flag was off.

## [3.3.0] - 2018-08-27
### Added:
- The new `LDClient` method `allFlagsState()` should be used instead of `allFlags()` if you are passing flag data to the front end for use with the JavaScript SDK. It preserves some flag metadata that the front end requires in order to send analytics events correctly. Versions 2.5.0 and above of the JavaScript SDK are able to use this metadata, but the output of `allFlagsState()` will still work with older versions.
- The `allFlagsState()` method also allows you to select only client-side-enabled flags to pass to the front end, by using the option `clientSideOnly => true`.

### Deprecated:
- `LDClient.allFlags()`

## [3.2.1] - 2018-07-16
### Fixed:
- The `LDClient::VERSION` constant has been fixed to report the current version. In the previous release, it was still set to 3.1.0.

## [3.2.0] - 2018-06-26
### Changed:
- The client now treats most HTTP 4xx errors as unrecoverable: that is, after receiving such an error, it will take the client offline (for the lifetime of the client instance, which in most PHP applications is just the current request-response cycle). This is because such errors indicate either a configuration problem (invalid SDK key) or a bug, which is not likely to resolve without a restart or an upgrade. This does not apply if the error is 400, 408, 429, or any 5xx error.

### Fixed:
- Made various changes to project settings to improve the IDE experience and the build; enforced PSR-2 coding style. (Thanks, [localheinz](https://github.com/launchdarkly/php-client/pulls?utf8=%E2%9C%93&q=is%3Aclosed+is%3Apr+author%3Alocalheinz)!)

## [3.1.0] - 2018-04-30
### Added
- Analytics events for feature evaluations now have a `variation` property (the variation index) as well as `value`. This will allow for better performance in future versions of [`ld-relay`](https://github.com/launchdarkly/ld-relay) when it is used with the PHP client.
### Fixed
- Fixed a bug that made segment-based rules always fall through when using `LDDFeatureRequester`.

## [3.0.0] - 2018-02-21
### Added
- Support for a new LaunchDarkly feature: reusable user segments.

## [2.5.0] - 2018-02-13
### Added
- Adds support for a future LaunchDarkly feature, coming soon: semantic version user attributes.

## [2.4.0] - 2018-01-04
### Added
- Support for [private user attributes](https://docs.launchdarkly.com/docs/private-user-attributes).

### Changed
- Stop retrying HTTP requests if the API key has been invalidated.
- User bucketing supports integer attributes. Thanks @mlund01!
- Source code complies with the PSR-2 standard. Thanks @valerianpereira!

### Fixed
- The PSR-4 autoloading specification is now correct. Thanks @jenssegers!

## [2.3.0] - 2017-10-06
### Added
- New `flush` method forces events to be published to the LaunchDarkly service. This can be useful if `LDClient` is not automatically destroyed at the end of a request. Thanks @foxted!

### Fixed
- Documentation comment references the correct namespace for `CacheStorageInterface`. Thanks @pmeth!

## [2.2.0] - 2017-06-06
### Added
- Support for [publishing events via ld-relay](README.md#using-ld-relay)
- Allow `EventPublisher` to be injected into the client.
- `GuzzleEventPublisher` as a synchronous, in-process alternative to publishing events via background processes.
- Allow the `curl` path used by `CurlEventPublisher` to be customized via the `curl` option to `LDClient`. Thanks @abacaphiliac!

## [2.1.2] - 2017-04-27
### Changed
- Relaxed the requirement on `kevinrob/guzzle-cache-middleware` for the default `GuzzleFeatureRequester`.
- Added package suggestions in `composer.json`.

### Fixed
- Better handling of possibly null variations. Thanks @idirouhab!

## [2.1.1] - 2017-04-11
### Changed
- Better handling of possibly null targets. Thanks @idirouhab!
- Better handling of possibly null rules.

## [2.1.0] - 2017-03-23
### Changed
- Allow FeatureRequester to be injected into the client. Thanks @abacaphiliac!
- Allow Predis\Client to be overriden in LDDFeatureRequester. Thanks @abacaphiliac!
- Use logger interface method instead of Monolog method. Thanks @abacaphiliac!
- Improve type hinting for default. Thanks @jdrieghe!

## [2.0.7] - 2017-03-03
### Changed
- Removed warning when calling `allFlags` via `LDDFeatureRequester`
- Removed warning when a feature flag's prerequisites happen to be null

## [2.0.6] - 2017-02-07
### Changed
- Use minimum versions in composer.json

## [2.0.5] - 2017-02-03
### Changed
- Made Monolog dependency version less strict

## [2.0.4] - 2017-01-26
### Changed
- Made Composer requirements less strict

## [2.0.3] - 2017-01-05
### Changed
- Fixed botched 2.0.2 release: Better handling of null vs false when evaluating.

## [2.0.2] - 2017-01-04
### Changed
- Better handling of null vs false when evaluating.

## [2.0.1] - 2016-11-09
### Added
- Monolog is now a required dependency

## [2.0.0] - 2016-08-08
### Added
- Support for multivariate feature flags. In addition to booleans, feature flags can now return numbers, strings, dictionaries, or arrays via the `variation` method.
- New `allFlags` method returns all flag values for a specified user.
- New `secureModeHash` function computes a hash suitable for the new LaunchDarkly JavaScript client's secure mode feature.

### Changed
- The `FeatureRep` data model has been replaced with `FeatureFlag`. `FeatureFlag` is not generic.

### Deprecated
- The `toggle` call has been deprecated in favor of `variation`.

### Removed
- The `getFlag` function has been removed.
