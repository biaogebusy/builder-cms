#!/bin/sh
# Copy source only into a fresh, network-isolated container; never mount an existing site.
set -eu
: "${CHAT_TEST_IMAGE:?Set a local PHP 8.3+ image with Drupal and SQLite extensions}"
root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../../.." && pwd)
docker image inspect "$CHAT_TEST_IMAGE" >/dev/null
container=$(docker create --network none --entrypoint sh "$CHAT_TEST_IMAGE" -c 'sleep 1800')
trap 'docker rm -f "$container" >/dev/null' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$container" >/dev/null
docker exec "$container" php -r 'file_put_contents(PHP_CONFIG_FILE_SCAN_DIR . "/99-metadata-test.ini", "extension=pdo_sqlite\n");'
docker exec "$container" mkdir -p /app/docroot/sites/default /app/docroot/modules/contrib
cd "$root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/custom/xinshi_chat docroot/modules/custom/xinshi_ai_reference \
  docroot/modules/custom/xinshi_ai docroot/modules/contrib/simple_oauth \
  docroot/modules/contrib/consumers docroot/modules/contrib/jsonapi_extras | docker cp - "$container":/app
docker exec -e XINSHI_CHAT_ISOLATED_TEST=1 "$container" php \
  /app/docroot/modules/custom/xinshi_ai/tests/log-metadata/integration.php
