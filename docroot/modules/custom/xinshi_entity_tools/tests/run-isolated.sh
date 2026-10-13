#!/bin/sh
# Copy only source and dependencies into a disposable synthetic Drupal site.
set -eu
: "${ENTITY_TOOLS_TEST_IMAGE:?Set a local PHP 8.3+ image with Drupal extensions and pdo_sqlite}"
root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../.." && pwd)
docker image inspect "$ENTITY_TOOLS_TEST_IMAGE" >/dev/null
container=$(docker create --network none --entrypoint sh "$ENTITY_TOOLS_TEST_IMAGE" -c 'sleep 1800')
trap 'docker rm -f "$container" >/dev/null' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$container" >/dev/null
docker exec "$container" mkdir -p /app/docroot/sites/default /app/docroot/modules/contrib
cd "$root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php \
  docroot/core docroot/modules/custom/xinshi_entity_tools \
  docroot/modules/contrib/simple_oauth docroot/modules/contrib/consumers | docker cp - "$container":/app
docker exec "$container" sh -c \
  'find /app/docroot/modules/custom/xinshi_entity_tools \( -name "*.php" -o -name "*.module" -o -name "*.install" \) -exec php -l {} \;'
docker exec -e XINSHI_ENTITY_TOOLS_ISOLATED_TEST=1 "$container" php -d extension=pdo_sqlite \
  /app/docroot/modules/custom/xinshi_entity_tools/tests/integration.php
docker exec -e XINSHI_ENTITY_TOOLS_ISOLATED_TEST=1 "$container" php -d extension=pdo_sqlite \
  /app/docroot/modules/custom/xinshi_entity_tools/tests/http.php
