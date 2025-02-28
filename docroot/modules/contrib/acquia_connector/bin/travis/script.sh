#!/usr/bin/env bash

# NAME
#     script.sh - Run tests
#
# SYNOPSIS
#     script.sh
#
# DESCRIPTION
#     Runs static code analysis and automated tests.

cd "$(dirname "$0")"; source _includes.sh

cd ${TRAVIS_BUILD_DIR}

if [ "$CUSTOM_TEST" == "css" ]; then
  csslint --config=vendor/drupal/core/assets/scaffold/files/csslintrc .
fi
