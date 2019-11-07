#!/bin/bash

# Update version in LDClient class
LDCLIENT_PHP=src/LaunchDarkly/LDClient.php
LDCLIENT_PHP_TEMP=${LDCLIENT_PHP}.tmp
sed "s/const VERSION = '.*'/const VERSION = '${LD_RELEASE_VERSION}'/g" ${LDCLIENT_PHP} > ${LDCLIENT_PHP_TEMP}
mv ${LDCLIENT_PHP_TEMP} ${LDCLIENT_PHP}
