test:
	php -d xdebug.mode=coverage vendor/bin/phpunit

lint:
	./vendor/bin/psalm --no-cache
	composer cs-check


TEMP_TEST_OUTPUT=/tmp/sse-contract-test-service.log

# TEST_HARNESS_PARAMS can be set to add -skip parameters for any contract tests that cannot yet pass
# Explanation of current skips:
# - "evaluation/parameterized/attribute references/array index is not supported": Due to how PHP
#   arrays work, there's no way to disallow an array index lookup without breaking object property
#   lookups for properties that are numeric strings.
#
# - "big segments/membership caching/context cache eviction (cache size)": Caching is provided through
#   PSR-6 (psr/cache) interface. This interface does not provide a way to limit the cache size. The
#   test harness expects the cache to evict items when the cache size is exceeded. This is not possible
#   with the current implementation.
TEST_HARNESS_PARAMS := $(TEST_HARNESS_PARAMS) \
	-skip 'evaluation/parameterized/attribute references/array index is not supported' \
	-skip 'big segments/membership caching/context cache eviction (cache size)'

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
