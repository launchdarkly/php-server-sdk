# SDK contract test service

This directory contains an implementation of the cross-platform SDK testing protocol defined by https://github.com/launchdarkly/sdk-test-harness. See that project's `README` for details of this protocol, and the kinds of SDK capabilities that are relevant to the contract tests. This code should not need to be updated unless the SDK has added or removed such capabilities.

To run these tests locally, run `make contract-tests` from the SDK project root directory. This downloads the correct version of the test harness tool automatically.

Or, to test against an in-progress local version of the test harness, run `make start-contract-test-service` from the SDK project root directory; then, in the root directory of the `sdk-test-harness` project, build the test harness and run it from the command line.

The test service is designed to be run by any web server; PHP's built-in development server is adequate. The server must be configured to run `index.php` for all requests. The environment variable `LD_TEST_SERVICE_DATA_DIR` can be set, if desired, to point to a specific file path where the test service can store data; otherwise it will create a directory under `/tmp`.
