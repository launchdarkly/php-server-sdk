# Change log

All notable changes to the LaunchDarkly PHP SDK will be documented in this file. This project adheres to [Semantic Versioning](http://semver.org).

## [6.7.0](https://github.com/launchdarkly/php-server-sdk/compare/6.6.2...6.7.0) (2026-02-23)


### Features

* Allow providing PSR-6 cache for feature requesters ([#235](https://github.com/launchdarkly/php-server-sdk/issues/235)) ([2328808](https://github.com/launchdarkly/php-server-sdk/commit/232880881e5895815d3ddad994e1fc66e8fa1d7b)), closes [#234](https://github.com/launchdarkly/php-server-sdk/issues/234)

## [6.6.2](https://github.com/launchdarkly/php-server-sdk/compare/6.6.1...6.6.2) (2025-11-20)


### Bug Fixes

* Improve escaping for curl event publisher ([#230](https://github.com/launchdarkly/php-server-sdk/issues/230)) ([bff2a4a](https://github.com/launchdarkly/php-server-sdk/commit/bff2a4af6bc475cee23b850f9234ebe5e35cc861))


### Miscellaneous Chores

* Add latest versions of PHP to CI ([#227](https://github.com/launchdarkly/php-server-sdk/issues/227)) ([9836192](https://github.com/launchdarkly/php-server-sdk/commit/9836192cedae0e392823de60de00076adee89a50))

## [6.6.1](https://github.com/launchdarkly/php-server-sdk/compare/6.6.0...6.6.1) (2025-10-20)


### Bug Fixes

* Handle all exceptions within Guzzle feature requester ([#226](https://github.com/launchdarkly/php-server-sdk/issues/226)) ([361f1fe](https://github.com/launchdarkly/php-server-sdk/commit/361f1fef47182203e3ead1e19aa13b067cc90955)), closes [#225](https://github.com/launchdarkly/php-server-sdk/issues/225)

## [6.6.0](https://github.com/launchdarkly/php-server-sdk/compare/6.5.2...6.6.0) (2025-04-08)


### Features

* Include instance ID header across API requests ([#221](https://github.com/launchdarkly/php-server-sdk/issues/221)) ([4f87f4c](https://github.com/launchdarkly/php-server-sdk/commit/4f87f4cd37bdf93b61f8c9270d3d5821decf8b1b))


### Miscellaneous Chores

* Add user agent to .sdk_metadata.json ([#224](https://github.com/launchdarkly/php-server-sdk/issues/224)) ([cffa148](https://github.com/launchdarkly/php-server-sdk/commit/cffa148695755d45f2dd2b1fa1dd577133f22d8e))

## [6.5.2](https://github.com/launchdarkly/php-server-sdk/compare/6.5.1...6.5.2) (2025-03-25)


### Bug Fixes

* Honor `timeout` configuration for CurlEventPublisher ([#216](https://github.com/launchdarkly/php-server-sdk/issues/216)) ([1c969be](https://github.com/launchdarkly/php-server-sdk/commit/1c969be38e01e80dc29e202b1a46373dfeb35196))

## [6.5.1](https://github.com/launchdarkly/php-server-sdk/compare/6.5.0...6.5.1) (2025-03-17)


### Bug Fixes

* php 8.4 deprecation for implicitly nullable parameters ([#219](https://github.com/launchdarkly/php-server-sdk/issues/219)) ([1143a16](https://github.com/launchdarkly/php-server-sdk/commit/1143a16bbc537c7cc076e8e7022be1cb81b4087c))

## [6.5.0](https://github.com/launchdarkly/php-server-sdk/compare/6.4.0...6.5.0) (2025-03-13)


### Features

* Inline context for migration events ([#215](https://github.com/launchdarkly/php-server-sdk/issues/215)) ([99e9593](https://github.com/launchdarkly/php-server-sdk/commit/99e9593f48662ccca56fed046ff7603ef99dacc9))

## [6.4.0](https://github.com/launchdarkly/php-server-sdk/compare/6.3.0...6.4.0) (2025-01-17)


### Features

* Add support for big segments ([#213](https://github.com/launchdarkly/php-server-sdk/issues/213)) ([bd6c50c](https://github.com/launchdarkly/php-server-sdk/commit/bd6c50c427e7bbb78171d00a6c1d337792822264))

## [6.3.0](https://github.com/launchdarkly/php-server-sdk/compare/6.2.0...6.3.0) (2024-10-22)


### Features

* Add support for client-side prerequisite events ([#210](https://github.com/launchdarkly/php-server-sdk/issues/210)) ([a940b34](https://github.com/launchdarkly/php-server-sdk/commit/a940b342352c88ad899ef867b2e3093eb6374666))

## [6.2.0](https://github.com/launchdarkly/php-server-sdk/compare/6.1.0...6.2.0) (2024-06-11)


### Features

* Add wrapper_name and wrapper_version configuration options ([#207](https://github.com/launchdarkly/php-server-sdk/issues/207)) ([9deddf0](https://github.com/launchdarkly/php-server-sdk/commit/9deddf08ebf2a0e55f5e214375de0667ecb2955c))


### Miscellaneous Chores

* add .sdk_metadata.json ([#204](https://github.com/launchdarkly/php-server-sdk/issues/204)) ([cc13724](https://github.com/launchdarkly/php-server-sdk/commit/cc137241688862ec5b821211c9c63b1fcb783f8d))

## [6.1.0](https://github.com/launchdarkly/php-server-sdk/compare/6.0.2...6.1.0) (2024-03-15)

> [!WARNING]
> If you are using the [Relay Proxy](https://github.com/launchdarkly/ld-relay/), please make sure to update to v8.4.0 **before** updating to this version of the SDK.
> Failure to do so will affect the events sent to LaunchDarkly and any support data export integrations.

### Features

* Redact anonymous attributes within feature events ([#193](https://github.com/launchdarkly/php-server-sdk/issues/193)) ([cdad89a](https://github.com/launchdarkly/php-server-sdk/commit/cdad89a38c9dbe16ae70ae2d260eab0471ec64f7))
* Update to event schema v4 ([#192](https://github.com/launchdarkly/php-server-sdk/issues/192)) ([475727f](https://github.com/launchdarkly/php-server-sdk/commit/475727f634abeecbad9cf678618b95909c341af4))


### Bug Fixes

* Replace deprecated utf8_encode usage ([#201](https://github.com/launchdarkly/php-server-sdk/issues/201)) ([2c05c8d](https://github.com/launchdarkly/php-server-sdk/commit/2c05c8d909d57e2855cde9dde2588b4ee53ca828)), closes [#199](https://github.com/launchdarkly/php-server-sdk/issues/199)

## [6.0.2](https://github.com/launchdarkly/php-server-sdk/compare/6.0.1...6.0.2) (2024-01-23)


### Bug Fixes

* Remove noisy log message about missing guzzle cache middleware ([#196](https://github.com/launchdarkly/php-server-sdk/issues/196)) ([e850026](https://github.com/launchdarkly/php-server-sdk/commit/e85002653dadc95e36fd828301bdb8ffa31532e3))

## [6.0.1](https://github.com/launchdarkly/php-server-sdk/compare/6.0.0...6.0.1) (2023-12-01)


### Bug Fixes

* **build:** Leverage .gitattributes to reduce package size ([#186](https://github.com/launchdarkly/php-server-sdk/issues/186)) ([b8cb035](https://github.com/launchdarkly/php-server-sdk/commit/b8cb03582b63cead74e45b50fa498380fb79cdd3))


### Miscellaneous Chores

* Fix package version in release please manifest ([#190](https://github.com/launchdarkly/php-server-sdk/issues/190)) ([cbdb529](https://github.com/launchdarkly/php-server-sdk/commit/cbdb5296569da112ec4d192c1d4805e7caf06c09))

## [6.0.0] - 2023-10-23
The latest version of this SDK supports the ability to manage migrations or modernizations, using migration flags. You might use this functionality if you are optimizing queries, upgrading to new tech stacks, migrating from one database to another, or other similar technology changes. Migration flags are part of LaunchDarkly's Early Access Program. This feature is available to all LaunchDarkly customers but may undergo additional changes before it is finalized.

For detailed information about this version, refer to the list below. For information on how to upgrade from the previous version, read the [migration guide](https://docs.launchdarkly.com/sdk/server-side/php/migration-5-to-6).

### Added:
- A new `Migrator` type which provides an out-of-the-box configurable migration framework.
- For more advanced use cases, added new `migrationVariation` and `trackMigrationOperation` methods on `LDClient`.

### Removed:
- PHP 8.0 support was removed.
- The legacy user format for contexts is no longer supported.  To learn more, read the [Contexts documentation](https://docs.launchdarkly.com/guides/flags/intro-contexts).
- Methods which originally took an `LDContext` or an `LDUser` now only accept an `LDContext`.
- Previously deprecated test data flag builder methods `variationForAllUsers`, `valueForAllUsers`, and `clearUserTargets` have been removed.

## [5.2.0] - 2023-10-23
### Deprecated:
- `LDUser` is now deprecated in favor of `LDContext`.

## [5.1.1] - 2023-07-12
### Changed:
- Invalid context log message now includes the flag key as part of the Psr\Log context. (Thanks, [mrtus](https://github.com/launchdarkly/php-server-sdk/pull/179)!)
- Error logging now includes the exception in the Psr\Log exception context key. (Thanks, [mrtus](https://github.com/launchdarkly/php-server-sdk/pull/177)!)

## [5.1.0] - 2023-01-31
### Added:
- Introduced support for an `application_info` config property which sets application metadata that may be used in LaunchDarkly analytics or other product features. This does not affect feature flag evaluations.

## [4.3.0] - 2023-01-31
### Added:
- Introduced support for an `application_info` config property which sets application metadata that may be used in LaunchDarkly analytics or other product features. This does not affect feature flag evaluations.

## [5.0.0] - 2023-01-04
The latest version of this SDK supports LaunchDarkly's new custom contexts feature. Contexts are an evolution of a previously-existing concept, "users." Contexts let you create targeting rules for feature flags based on a variety of different information, including attributes pertaining to users, organizations, devices, and more. You can even combine contexts to create "multi-contexts." 

For detailed information about this version, please refer to the list below. For information on how to upgrade from the previous version, please read the [migration guide](https://docs.launchdarkly.com/sdk/server-side/php/migration-4-to-5).

### Added:
- The type `LDContext` defines the new "context" model. "Contexts" are a replacement for the earlier concept of "users"; they can be populated with attributes in more or less the same way as before, but they also support new behaviors. To learn more, read [the documentation](https://docs.launchdarkly.com/home/contexts).
- For all SDK methods that took an `LDUser` parameter, the parameter type can now be either `LDUser` or `LDContext`. The SDK still supports `LDUser` for now, but `LDContext` is the preferred model and `LDUser` may be removed in a future version.

### Changed _(breaking changes from 4.x)_:
- It was previously allowable to set a user key to an empty string. In the new context model, the key is not allowed to be empty. Trying to use an empty key will cause evaluations to fail and return the default value.
- There is no longer such a thing as a `secondary` meta-attribute that affects percentage rollouts. If you set an attribute with that name in `LDContext`, it will simply be a custom attribute like any other.
- Component interface types like `EventPublisher` and `FeatureRequester` which applications are unlikely to reference directly (except when defining a custom component implementation) have been moved out of the main namespace into a new namespace, `LaunchDarkly\Subsystems`.

### Changed (requirements/dependencies/build):
- The minimum PHP version is now 8.0.

### Changed (behavioral changes):
- The SDK is now more strictly compliant with the LaunchDarkly specification for date and semantic version values. This means that some values that might have been accepted in the past, which other SDKs would not accept, are now correctly considered invalid. Please review the LaunchDarkly documentation on [Using date/time and semantic version operators](https://docs.launchdarkly.com/sdk/concepts/flag-types/?q=representing+date+time+values#using-datetime-and-semantic-version-operators).
- Analytics event data now uses a new JSON schema due to differences between the context model and the old user model.

### Fixed:
- Fixed a bug in the parsing of string values in feature flags and user attributes when they were referenced with date/time operators in a targeting rule. As described in [LaunchDarkly documentation](https://docs.launchdarkly.com/sdk/concepts/flag-types#representing-datetime-values), such values must use the RFC3339 date/time format; the SDK was also accepting strings in other formats (for instance, ones that did not have a time or a time zone), which would cause undefined behavior inconsistent with evaluations done by other LaunchDarkly services. This fix ensures that all targeting rules that reference an invalid date/time value are a non-match, and does not affect how the SDK treats values that are in the correct format.
- The SDK was allowing numeric values to be treated as semantic versions in targeting rules. It now correctly only allows strings, as described in [LaunchDarkly documentation](https://docs.launchdarkly.com/sdk/concepts/flag-types#using-semantic-versions).

### Removed:
- Removed all types, fields, and methods that were deprecated as of the most recent 4.x release.
- Removed the `secondary` meta-attribute in `LDUser` and `LDUserBuilder`.
- The `alias` method no longer exists because alias events are not needed in the new context model.
- The `inline_users_in_events` option no longer exists because it is not relevant in the new context model.

## [4.2.4] - 2022-10-07
### Changed:
- CI builds now include a cross-platform test suite implemented in https://github.com/launchdarkly/sdk-test-harness. This is in addition to unit test coverage, and ensures consistent behavior across SDKs.

### Fixed:
- Setting a `base_uri` or `events_uri` with a non-empty path, such as `http://my-reverse-proxy-host/launchdarkly-requests`, did not work.
- The object returned by `allFlagsState()`, when converted to JSON, had an incorrect format in the case where no flags exist.

## [4.2.3] - 2022-09-07
### Changed:
- Expanded upper version restriction on [Monolog](https://github.com/Seldaek/monolog).

## [4.2.2] - 2022-08-01
### Fixed:
- The TestData class was incorrectly generating an error when updating a changed flag. (Thanks, [aretenz](https://github.com/launchdarkly/php-server-sdk/pull/161)!)

## [4.2.1] - 2022-07-05
### Changed:
- Expanded lower version restriction on [Guzzle](https://github.com/guzzle/guzzle).

## [4.2.0] - 2022-04-13
### Added:
- Add support for psr/log v2 and v3.
- The LaunchDarkly\Integrations\TestData is a new way to inject feature flag data programmatically into the SDK for testing—either with fixed values for each flag, or with targets and/or rules that can return different values for different users. Unlike LaunchDarkly\Integrations\Files, this mechanism does not use any external resources, only the data that your test code has provided.

## [4.1.0] - 2022-02-16
### Added:
- The curl command used for publishing events will now honor the connect_timeout parameter.
- Publishing events on Windows will now use PowerShell and Invoke-WebRequest instead of curl.

### Fixed:
- Numeric strings are no longer treated like numbers in equality checks.
- User attributes with values of 0 or false will no longer be filtered out of event payloads.
- When using allFlagsState to produce bootstrap data for the JavaScript SDK, the PHP SDK was not returning the correct metadata for evaluations that involved an experiment. As a result, the analytics events produced by the JavaScript SDK did not correctly reflect experimentation results.

## [4.0.0] - 2021-08-06
This major version release is for updating PHP compatibility, simplifying the SDK&#39;s dependencies, and removing deprecated names.

Except for the dependency changes described below which may require minor changes in your build, usage of the SDK has not changed in this release. For more details about changes that may be necessary, see the [3.x to 4.0 migration guide](https://docs.launchdarkly.com/sdk/server-side/php/migration-3-to-4).

Dropping support for obsolete PHP versions makes it easier to maintain the SDK and keep its dependencies up to date. See LaunchDarkly&#39;s [End of Life Policy](https://launchdarkly.com/policies/end-of-life-policy/) regarding platform version support.

Simplifying dependencies by moving optional integration features into separate packages reduces the size of the SDK bundle, as well as reducing potential compatibility problems and vulnerabilities.

### Added:
- Added type declarations to all methods. These could result in a `TypeError` at runtime if you have been passing values of the wrong types to SDK methods (including passing a `null` value for a parameter that should not be null)-- although in most cases, this would have caused an error anyway at some point in the SDK code, just not such a clearly identifiable error. To detect type mistakes before runtime, you can use a static analysis tool such as [Psalm](https://psalm.dev/).

### Changed:
- The minimum PHP version is now 7.3.
- Updated many dependencies to newer versions and/or more actively maintained packages.

### Removed:
- Removed the bundled Redis, DynamoDB, and Consul integrations. These are now provided as separate packages; see [php-server-sdk-redis-predis](https://github.com/launchdarkly/php-server-sdk-redis-predis), [php-server-sdk-redis-phpredis](https://github.com/launchdarkly/php-server-sdk-redis-phpredis), [php-server-sdk-dynamodb](https://github.com/launchdarkly/php-server-sdk-dynamodb), and [php-server-sdk-consul](https://github.com/launchdarkly/php-server-sdk-consul).
- Removed all types and methods that were deprecated in the last 3.x version.
- Removed implementation types from the `LaunchDarkly` namespace that were annotated as `@internal` and not documented, such as types that are part of the internal feature data model. These are not meant for use by application code, and are always subject to change. They have been moved into `LaunchDarklyImpl`.


## [3.9.1] - 2021-08-02
### Fixed:
- The `phpredis` integration was ignoring the `phpredis_client` option for passing in a preconfigured Redis client. (Thanks, [CameronHall](https://github.com/launchdarkly/php-server-sdk/pull/151)!)

## [3.9.0] - 2021-06-21
### Added:
- The SDK now supports the ability to control the proportion of traffic allocation to an experiment. This works in conjunction with a new platform feature now available to early access customers.


## [3.8.0] - 2021-04-19
### Added:
- Added the `alias` method to `LDClient`. This can be used to associate two user objects for analytics purposes with an alias event.


## [3.7.6] - 2021-04-14
### Fixed:
- When using `Files.featureRequester`, if a data file did not contain valid JSON, the SDK would throw a PHP syntax error instead of the expected &#34;File is not valid JSON&#34; error. (Thanks, [GuiEloiSantos](https://github.com/launchdarkly/php-server-sdk/pull/145)!)

## [3.7.5] - 2021-03-01
### Fixed:
- `PHPRedis::featureRequester` was not recognizing the `phpredis_client` option. (Thanks, [riekelt](https://github.com/launchdarkly/php-server-sdk/pull/143)!)

## [3.7.4] - 2021-01-07
### Fixed:
- Fixed a warning message which erroneously referred to the wrong method.

## [3.7.3] - 2020-10-28
### Fixed:
- When using the DynamoDB data store integration with a prefix string, the prefix was being prepended to keys with a slash separator (example: `my-prefix/features:my-flag-key`). This was inconsistent with the colon separator that is used in the other server-side SDKs (example: `my-prefix:features:my-flag-key`), making the PHP SDK unable to read flags that were put into the database by other SDKs or by the Relay Proxy, if a prefix was used. This has been fixed to be consistent with the other SDKs. ([#138](https://github.com/launchdarkly/php-server-sdk/issues/138))

## [3.7.2] - 2020-04-24
### Fixed:
- The SDK could try to send analytics events even if `send_events` had been set to false. This bug was introduced in the 3.6.0 release.
- A `use` statement with the wrong namespace was causing Composer to print a deprecation warning. (Thanks, [bfenton-smugmug](https://github.com/launchdarkly/php-server-sdk/pull/134)!)


## [3.7.1] - 2020-01-03
### Fixed:
- Loosened the Monolog dependency constraint so that it will accept either a 1.x or a 2.x version. This should be compatible with all currently supported PHP versions; the SDK's use of Monolog does not rely on any features that are specific to 1.x. (Thanks, [mrtus](https://github.com/launchdarkly/php-server-sdk/pull/132)!)
- In rare circumstances (depending on the exact data in the flag configuration, the flag's salt value, and the user properties), a percentage rollout could fail and return a default value, logging the error "Data inconsistency in feature flag ... variation/rollout object with no variation or rollout". This would happen if the user's hashed value fell exactly at the end of the last "bucket" (the last variation defined in the rollout). This has been fixed so that the user will get the last variation.

## [3.7.0] - 2019-12-13
### Added:
- Added integration with the [`phpredis`](https://github.com/phpredis/phpredis) extension, which has similar functionality to the already-supported `predis` but may have better performance (since `predis` is written in pure PHP, whereas `phpredis` uses a C extension). See `LaunchDarkly::Integrations::PHPRedis`. (Thanks, [nicofff](https://github.com/launchdarkly/php-server-sdk/pull/128)!)

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
- It is now possible to use Consul or DynamoDB as a data store with `ld-relay`, similar to the existing Redis integration. See `LaunchDarkly\Integrations\Consul` and `LaunchDarkly\Integrations\DynamoDb`, and the reference guide [Persistent data stores](https://docs.launchdarkly.com/sdk/concepts/data-stores).
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
- Support for [private user attributes](https://docs.launchdarkly.com/home/users/attributes#creating-private-user-attributes).

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
