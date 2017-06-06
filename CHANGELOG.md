# Change log

All notable changes to the LaunchDarkly PHP SDK will be documented in this file. This project adheres to [Semantic Versioning](http://semver.org).

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
