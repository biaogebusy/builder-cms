#!/bin/sh
# Validate supplier-cost changes without mounting a site database or credentials.
set -eu
: "${USAGE_TEST_PHP_IMAGE:?Set a local PHP 8.3+ image with pdo_sqlite and pdo_mysql}"
: "${USAGE_TEST_PHPUNIT_PHAR:?Set the path to a PHPUnit 11 PHAR}"
usage_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../.." && pwd)
usage_container=$(docker create --network none --entrypoint sh "$USAGE_TEST_PHP_IMAGE" -c 'sleep 3600')
usage_database=''
usage_cleanup() {
  if [ -n "$usage_database" ]; then docker rm -fv "$usage_database" >/dev/null; fi
  docker rm -fv "$usage_container" >/dev/null
}
trap usage_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$usage_container" >/dev/null
docker exec "$usage_container" mkdir -p /workspace
cd "$usage_root"
tar -cf - vendor docroot/core docroot/modules/custom/xinshi_ai_usage \
  docroot/modules/custom/xinshi_ai/src docroot/modules/custom/xinshi_ai/xinshi_ai.module \
  | docker cp - "$usage_container":/workspace
docker cp "$USAGE_TEST_PHPUNIT_PHAR" "$usage_container":/phpunit.phar
docker exec "$usage_container" sh -c 'find /workspace/docroot/modules/custom/xinshi_ai_usage \( -name "*.php" -o -name "*.install" \) -exec php -l {} \;'
docker exec "$usage_container" php -d extension=pdo_sqlite -d zend.assertions=0 /phpunit.phar \
  --do-not-cache-result --bootstrap /workspace/docroot/modules/custom/xinshi_ai_usage/tests/bootstrap.php \
  /workspace/docroot/modules/custom/xinshi_ai_usage/tests/src/Unit
if [ -n "${USAGE_TEST_MARIADB_IMAGE:-}" ]; then
  usage_database=$(docker run -d --network "container:$usage_container" \
    -e MYSQL_ROOT_PASSWORD=isolated-root -e MYSQL_DATABASE=usage_backfill_test \
    -e MYSQL_USER=usage_test -e MYSQL_PASSWORD=isolated-test "$USAGE_TEST_MARIADB_IMAGE")
  docker exec "$usage_container" php -d extension=pdo_mysql -r '
    for ($i = 0; $i < 60; $i++) {
      try { new PDO("mysql:host=127.0.0.1;dbname=usage_backfill_test", "usage_test", "isolated-test"); exit(0); }
      catch (Throwable $error) { usleep(500000); }
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1);'
  docker exec -e XINSHI_USAGE_ISOLATED_MYSQL=1 "$usage_container" php \
    -d extension=pdo_sqlite -d extension=pdo_mysql -d zend.assertions=0 /phpunit.phar \
    --do-not-cache-result --bootstrap /workspace/docroot/modules/custom/xinshi_ai_usage/tests/bootstrap.php \
    /workspace/docroot/modules/custom/xinshi_ai_usage/tests/src/Unit/CostBackfillTest.php
fi
