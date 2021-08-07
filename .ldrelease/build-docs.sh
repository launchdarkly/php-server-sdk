#!/bin/bash

# This script assumes that it is running in a Docker container using the image
# "ldcircleci/php-sdk-release", defined in https://github.com/launchdarkly/sdks-ci-docker

set -e

cd docs
make
