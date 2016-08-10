# Change log

All notable changes to the LaunchDarkly Java SDK will be documented in this file. This project adheres to [Semantic Versioning](http://semver.org).

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