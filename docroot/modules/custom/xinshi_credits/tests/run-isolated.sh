#!/bin/sh
# Copy code into a disposable network-none container, without site files or credentials.
set -eu
: "${CREDITS_TEST_IMAGE:?Set a local PHP 8.3+ image with Drupal extensions and pdo_sqlite}"
credits_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../.." && pwd)
credits_container=$(docker create --network none --entrypoint sh "$CREDITS_TEST_IMAGE" -c 'sleep 3600')
credits_database=''
credits_mysql=0
credits_cleanup() {
  if [ -n "${CREDITS_TEST_ARTIFACTS:-}" ]; then
    mkdir -p "$CREDITS_TEST_ARTIFACTS"
    docker cp "$credits_container":/tmp/credits-test/ui/. "$CREDITS_TEST_ARTIFACTS"/ 2>/dev/null || true
    docker cp "$credits_container":/tmp/credits-test/http.log "$CREDITS_TEST_ARTIFACTS"/ 2>/dev/null || true
  fi
  if [ -n "$credits_database" ]; then docker rm -fv "$credits_database" >/dev/null; fi
  docker rm -fv "$credits_container" >/dev/null
}
trap credits_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$credits_container" >/dev/null
if [ -n "${CREDITS_TEST_MARIADB_IMAGE:-}" ]; then
  credits_mysql=1
  credits_database=$(docker run -d --network "container:$credits_container" \
    -e MYSQL_ROOT_PASSWORD=isolated-root -e MYSQL_DATABASE=credits_test \
    -e MYSQL_USER=credits_test -e MYSQL_PASSWORD=isolated-test \
    "$CREDITS_TEST_MARIADB_IMAGE")
  if ! docker exec "$credits_container" php -d extension=pdo_mysql -r '
    for ($i = 0; $i < 60; $i++) {
      try { new PDO("mysql:host=127.0.0.1;dbname=credits_test", "credits_test", "isolated-test"); exit(0); }
      catch (Throwable $error) { usleep(500000); }
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1);'; then
    docker logs "$credits_database" >&2
    exit 1
  fi
fi
docker exec "$credits_container" mkdir -p /app/docroot/sites/default /app/docroot/modules/contrib
cd "$credits_root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/custom/xinshi_credits docroot/modules/custom/xinshi_rewards \
  docroot/modules/custom/xinshi_ai_usage docroot/modules/custom/xinshi_ai_billing \
  docroot/modules/custom/xinshi_ai/src docroot/modules/custom/xinshi_ai/xinshi_ai.install docroot/modules/contrib/simple_oauth \
  docroot/modules/contrib/consumers | docker cp - "$credits_container":/app
docker exec "$credits_container" sh -c 'find /app/docroot/modules/custom/xinshi_credits \( -name "*.php" -o -name "*.module" -o -name "*.install" \) -exec php -l {} \;'
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 -e CREDITS_TEST_MYSQL="$credits_mysql" "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_credits/tests/integration.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_credits/tests/configuration.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_credits/tests/upgrade.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_credits/tests/http.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_credits/tests/concurrency.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_credits/tests/lifecycle.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_credits/tests/lifecycle-concurrency.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_credits/tests/publication.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_credits/tests/publication-concurrency.php
docker exec "$credits_container" sh -c 'find /app/docroot/modules/custom/xinshi_rewards \( -name "*.php" -o -name "*.install" \) -exec php -l {} \;'
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_rewards/tests/integration.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_rewards/tests/http.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_rewards/tests/concurrency.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_credits/tests/configuration.php
docker exec "$credits_container" sh -c 'find /app/docroot/modules/custom/xinshi_ai_billing \( -name "*.php" -o -name "*.install" \) -exec php -l {} \;'
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_ai_billing/tests/integration.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_ai_billing/tests/http.php
docker exec -e XINSHI_CREDITS_ISOLATED_TEST=1 "$credits_container" php -d extension=pdo_sqlite -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_ai_billing/tests/images.php
