#!/bin/sh
# Copy code into a disposable network-none container, without site files or credentials.
set -eu
: "${MEDIA_TEST_IMAGE:?Set a local PHP 8.3+ image with Drupal extensions and pdo_sqlite}"
media_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../.." && pwd)
media_container=$(docker create --network none --entrypoint sh "$MEDIA_TEST_IMAGE" -c 'sleep 3600')
trap 'docker rm -f "$media_container" >/dev/null' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$media_container" >/dev/null
docker exec "$media_container" mkdir -p /app/docroot/sites/default /app/docroot/modules/contrib
cd "$media_root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/custom/xinshi_media docroot/modules/contrib/simple_oauth \
  docroot/modules/contrib/consumers | docker cp - "$media_container":/app
docker exec "$media_container" sh -c 'find /app/docroot/modules/custom/xinshi_media \( -name "*.php" -o -name "*.module" -o -name "*.install" \) -exec php -l {} \;'
docker exec -e XINSHI_MEDIA_ISOLATED_TEST=1 "$media_container" php -d extension=pdo_sqlite \
  /app/docroot/modules/custom/xinshi_media/tests/integration.php
docker exec -e XINSHI_MEDIA_ISOLATED_TEST=1 "$media_container" php -d extension=pdo_sqlite \
  /app/docroot/modules/custom/xinshi_media/tests/http.php
