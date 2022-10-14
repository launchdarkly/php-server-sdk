
test:
	php -d xdebug.mode=coverage vendor/bin/phpunit

lint:
	./vendor/bin/psalm --no-cache
	composer cs-check


TEMP_TEST_OUTPUT=/tmp/sse-contract-test-service.log

# TEST_HARNESS_PARAMS can be set to add -skip parameters for any contract tests that cannot yet pass
# Explanation of current skips:
# - "evaluation/parameterized/prerequisites": Can't pass yet because prerequisite cycle detection is not implemented.
# - various other "evaluation" subtests: These tests require context kind support.
# - "events": These test suites will be unavailable until more of the U2C implementation is done.
TEST_HARNESS_PARAMS := $(TEST_HARNESS_PARAMS) \
	-skip 'evaluation/bucketing/bucket by non-key attribute' \
	-skip 'evaluation/bucketing/secondary' \
	-skip 'evaluation/bucketing/selection of context' \
	-skip 'evaluation/parameterized/attribute references' \
	-skip 'evaluation/parameterized/bad attribute reference errors' \
	-skip 'evaluation/parameterized/prerequisites' \
	-skip 'evaluation/parameterized/segment match/included list is specific to user kind' \
	-skip 'evaluation/parameterized/segment match/includedContexts' \
	-skip 'evaluation/parameterized/segment match/excluded list is specific to user kind' \
	-skip 'evaluation/parameterized/segment match/excludedContexts' \
	-skip 'evaluation/parameterized/segment recursion' \
	-skip 'evaluation/parameterized/target match/context targets' \
	-skip 'evaluation/parameterized/target match/multi-kind' \
	-skip 'events'

build-contract-tests:
	@cd test-service && composer install --no-progress

start-contract-test-service: build-contract-tests
	@cd test-service && php -S localhost:8000 index.php

start-contract-test-service-bg:
	@echo "Test service output will be captured in $(TEMP_TEST_OUTPUT)"
	@make start-contract-test-service >$(TEMP_TEST_OUTPUT) 2>&1 &

run-contract-tests:
	@curl -s https://raw.githubusercontent.com/launchdarkly/sdk-test-harness/main/downloader/run.sh \
      | VERSION=v2 PARAMS="-url http://localhost:8000 -debug -stop-service-at-end $(TEST_HARNESS_PARAMS)" sh

contract-tests: build-contract-tests start-contract-test-service-bg run-contract-tests

.PHONY: build-contract-tests start-contract-test-service start-contract-test-service-bg run-contract-tests contract-tests
