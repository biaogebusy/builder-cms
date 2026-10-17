#!/bin/sh
# Copy sources and dependencies into a disposable container with no network or site mounts.
set -eu
: "${SHOOTING_TEST_IMAGE:?Set a local PHP 8.3+ image with Drupal extensions}"
: "${SHOOTING_PHPUNIT_PHAR:?Set the path to PHPUnit 11 phar}"
root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../.." && pwd)
docker image inspect "$SHOOTING_TEST_IMAGE" >/dev/null
container=$(docker create --network none --entrypoint sh "$SHOOTING_TEST_IMAGE" -c 'sleep 1800')
trap 'docker rm -f "$container" >/dev/null' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$container" >/dev/null
docker exec "$container" mkdir -p /app/docroot/sites/default /app/docroot/modules/contrib
cd "$root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/custom/xinshi_ai docroot/modules/custom/xinshi_ai_usage \
  docroot/modules/custom/xinshi_shooting_plan docroot/modules/contrib/ai \
  docroot/modules/contrib/ai_provider_openai docroot/modules/contrib/jsonapi_extras | docker cp - "$container":/app
docker cp "$SHOOTING_PHPUNIT_PHAR" "$container":/tmp/phpunit.phar
docker exec "$container" sh -c \
  'find /app/docroot/modules/custom/xinshi_shooting_plan \( -name "*.php" -o -name "*.module" -o -name "*.install" \) -exec php -l {} \;'
docker exec "$container" sh -c \
  'find /app/docroot/modules/custom/xinshi_ai/src -name "*.php" -exec php -l {} \;'
docker exec "$container" php -l /app/docroot/modules/custom/xinshi_ai/xinshi_ai.install
docker exec "$container" php -d extension=pdo_sqlite /tmp/phpunit.phar \
  --bootstrap /app/docroot/modules/custom/xinshi_ai/tests/bootstrap.php \
  --filter 'ImageJobExecutorTest|InputImageAccessTest|JobLifecycleServiceTest|ImageResponseSafetyTest' \
  /app/docroot/modules/custom/xinshi_ai/tests/src/Unit
docker exec -e XINSHI_SHOOTING_ISOLATED_TEST=1 "$container" php -d extension=pdo_sqlite /tmp/phpunit.phar \
  --bootstrap /app/docroot/modules/custom/xinshi_shooting_plan/tests/bootstrap.php \
  /app/docroot/modules/custom/xinshi_shooting_plan/tests/src/Kernel/ShootingSketchesTest.php
