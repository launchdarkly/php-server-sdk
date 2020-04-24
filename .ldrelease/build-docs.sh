#!/bin/bash

set -e

cd docs
make PHPDOC_ARCHIVE=/home/circleci/ldtools/phpDocumentor.phar  # provided in ldcircleci/ld-php-sdk-release image
