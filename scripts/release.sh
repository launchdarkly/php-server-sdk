#!/usr/bin/env bash
# This script updates the version in the client code. It does not need to do anything else to release
# a new version, because Packagist will pick up the new version automatically by watching our public
# repository.

# It takes exactly one argument: the new version.
# It should be run from the root of this git repo like this:
#   ./scripts/release.sh 4.0.9

# When done you should commit and push the changes made.

set -uxe
echo "Starting php-client release (version update only)"

VERSION=$1

echo $VERSION >./VERSION

# Update version in LDClient class
LDCLIENT_PHP=src/LaunchDarkly/LDClient.php
LDCLIENT_PHP_TEMP=./LDClient.php.tmp
sed "s/const VERSION = '.*'/const VERSION = '${VERSION}'/g" $LDCLIENT_PHP > $LDCLIENT_PHP_TEMP
mv $LDCLIENT_PHP_TEMP $LDCLIENT_PHP

echo "Done with php-client release (version update only)"
