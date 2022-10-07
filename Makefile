
TEMP_TEST_OUTPUT=/tmp/sse-contract-test-service.log

# TEST_HARNESS_PARAMS can be set to add -skip parameters for any contract tests that cannot yet pass
# Explanation of current skips:
# - "secondary": In the PHP SDK this is not an addressable attribute for clauses; in other
#   SDKs, it is. This was underspecified in the past; in future major versions, the other
#   SDKs and the contract tests will be in line with the PHP behavior.
# - "date - bad syntax", "semver - bad type": The PHP SDK has insufficiently strict
#   validation for these types. We will definitely fix this in 5.0 but may or may not
#   address it in 4.x, since it does not prevent any valid values from working.
TEST_HARNESS_PARAMS := $(TEST_HARNESS_PARAMS) \
	-skip 'evaluation/parameterized/secondary' \
	-skip 'evaluation/parameterized/operators - date - bad syntax' \
	-skip 'evaluation/parameterized/operators - semver - bad type'

build-contract-tests:
	@cd test-service && composer install --no-progress

start-contract-test-service: build-contract-tests
	@cd test-service && php -S localhost:8000 index.php

start-contract-test-service-bg:
	@echo "Test service output will be captured in $(TEMP_TEST_OUTPUT)"
	@make start-contract-test-service >$(TEMP_TEST_OUTPUT) 2>&1 &

run-contract-tests:
	@curl -s https://raw.githubusercontent.com/launchdarkly/sdk-test-harness/main/downloader/run.sh \
      | VERSION=v1 PARAMS="-url http://localhost:8000 -debug -stop-service-at-end $(TEST_HARNESS_PARAMS)" sh

contract-tests: build-contract-tests start-contract-test-service-bg run-contract-tests

.PHONY: build-contract-tests start-contract-test-service start-contract-test-service-bg run-contract-tests contract-tests
