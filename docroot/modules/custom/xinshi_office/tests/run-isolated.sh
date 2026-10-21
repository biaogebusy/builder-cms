#!/bin/sh
# Use only source copies in a disposable container without network or site mounts.
set -eu
: "${OFFICE_TEST_IMAGE:?Set a local PHP 8.3+ image with Drupal, GD, ZIP and SQLite extensions}"
office_test_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../.." && pwd)
docker image inspect "$OFFICE_TEST_IMAGE" >/dev/null
office_test_container=$(docker create --network none --entrypoint sh "$OFFICE_TEST_IMAGE" -c 'sleep 1800')
trap 'docker rm -f "$office_test_container" >/dev/null' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$office_test_container" >/dev/null
docker exec "$office_test_container" mkdir -p /app/docroot/sites/default /app/docroot/modules/contrib
cd "$office_test_root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/custom/xinshi_office docroot/modules/contrib/simple_oauth \
  docroot/modules/contrib/consumers | docker cp - "$office_test_container":/app
docker exec "$office_test_container" sh -c \
  'find /app/docroot/modules/custom/xinshi_office \( -name "*.php" -o -name "*.module" -o -name "*.install" \) -exec php -l {} \;'
docker exec -e XINSHI_OFFICE_ISOLATED_TEST=1 "$office_test_container" php -d extension=pdo_sqlite \
  /app/docroot/modules/custom/xinshi_office/tests/integration.php
docker exec -e XINSHI_OFFICE_ISOLATED_TEST=1 "$office_test_container" php -d extension=pdo_sqlite \
  /app/docroot/modules/custom/xinshi_office/tests/http.php

