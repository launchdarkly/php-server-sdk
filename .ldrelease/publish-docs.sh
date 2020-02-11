#!/bin/bash

set -e

# Releaser will publish the docs to GitHub pages for us if we put a "docs.zip" artifact in ./artifacts

mkdir -p artifacts
pushd docs/build/html
rm -f docs.zip
zip -r docs.zip *
popd
mv docs/build/html/docs.zip artifacts
