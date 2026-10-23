#!/bin/sh
# Install synthetic data only in a disposable container with an isolated source copy.
set -eu
: "${CONTENT_FORM_TEST_IMAGE:?Set a local PHP 8.3+ image with Drupal extensions and SQLite}"
root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../.." && pwd)
docker image inspect "$CONTENT_FORM_TEST_IMAGE" >/dev/null
container=$(docker create --network none --entrypoint sh "$CONTENT_FORM_TEST_IMAGE" -c 'sleep 1800')
trap 'docker rm -f "$container" >/dev/null' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$container" >/dev/null
docker exec "$container" mkdir -p /app/docroot/sites/default /app/docroot/modules/contrib
cd "$root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/custom/xinshi_api docroot/modules/contrib/entity_theme_engine \
  docroot/modules/contrib/simple_oauth docroot/modules/contrib/consumers \
  docroot/modules/contrib/jsonapi_extras | docker cp - "$container":/app
docker exec -e XINSHI_CONTENT_FORM_TEST=1 "$container" php -d extension=pdo_sqlite \
  /app/docroot/modules/custom/xinshi_api/tests/content-form-integration.php
docker exec -e XINSHI_CONTENT_FORM_TEST=1 "$container" php -d extension=pdo_sqlite \
  /app/docroot/modules/custom/xinshi_api/tests/content-form-http.php
