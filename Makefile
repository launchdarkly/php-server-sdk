
TEMP_TEST_OUTPUT=/tmp/sse-contract-test-service.log

# TEST_HARNESS_PARAMS can be set to add -skip parameters for any contract tests that cannot yet pass
TEST_HARNESS_PARAMS=

build-contract-tests:
	@docker build -f Dockerfile.testservice -t php-test-service .

start-contract-test-service: build-contract-tests
	@docker run -it --rm \
		--name php-test-service \
		--publish 8000:8000 \
		--add-host=host.docker.internal:host-gateway \
		php-test-service

start-contract-test-service-bg:
	@echo "Test service output will be captured in $(TEMP_TEST_OUTPUT)"
	@make start-contract-test-service >$(TEMP_TEST_OUTPUT) 2>&1 &

run-contract-tests:
	@curl -s https://raw.githubusercontent.com/launchdarkly/sdk-test-harness/main/downloader/run.sh \
      | VERSION=v1 PARAMS="-url http://localhost:8000 -debug -stop-service-at-end $(TEST_HARNESS_PARAMS)" sh

contract-tests: build-contract-tests start-contract-test-service-bg run-contract-tests

.PHONY: build-contract-tests start-contract-test-service start-contract-test-service-bg run-contract-tests contract-tests
