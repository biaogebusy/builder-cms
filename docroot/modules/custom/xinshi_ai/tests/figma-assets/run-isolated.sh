#!/bin/sh
# Copy sources into disposable containers. No existing site or database is mounted.
set -eu
: "${CHAT_TEST_IMAGE:?Set a local PHP 8.3+ image with GD and PDO SQLite/MySQL}"
figma_test_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../../.." && pwd)
figma_test_network=none
figma_test_db=
figma_test_container=
cleanup() {
  if [ -n "$figma_test_container" ]; then docker rm -fv "$figma_test_container" >/dev/null; fi
  if [ -n "$figma_test_db" ]; then docker rm -fv "$figma_test_db" >/dev/null; fi
  if [ "$figma_test_network" != none ]; then docker network rm "$figma_test_network" >/dev/null; fi
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker image inspect "$CHAT_TEST_IMAGE" >/dev/null
figma_test_mysql=0
if [ -n "${CHAT_TEST_DB_IMAGE:-}" ]; then
  docker image inspect "$CHAT_TEST_DB_IMAGE" >/dev/null
  figma_test_mysql=1
  figma_test_network=$(docker network create --internal "figma-import-$(date +%s)-$$")
  figma_test_db=$(docker create --memory 1g --cpus 2 --network "$figma_test_network" --network-alias chat-db \
    -e MYSQL_ROOT_PASSWORD=chat-test-only -e MYSQL_DATABASE=chat_test \
    -e MYSQL_USER=chat -e MYSQL_PASSWORD=chat-test-only "$CHAT_TEST_DB_IMAGE")
  docker start "$figma_test_db" >/dev/null
  figma_test_admin=$(docker exec "$figma_test_db" sh -c 'command -v mariadb-admin || command -v mysqladmin')
  figma_test_client=$(docker exec "$figma_test_db" sh -c 'command -v mariadb || command -v mysql')
  figma_test_attempt=0
  until docker exec "$figma_test_db" "$figma_test_admin" --protocol=TCP -h127.0.0.1 \
    ping -uroot -pchat-test-only --silent >/dev/null 2>&1; do
    figma_test_attempt=$((figma_test_attempt + 1))
    if [ "$figma_test_attempt" -ge 60 ]; then docker logs "$figma_test_db" >&2; exit 1; fi
    sleep 1
  done
  docker exec "$figma_test_db" "$figma_test_client" -uroot -pchat-test-only \
    -e "GRANT PROCESS ON *.* TO 'chat'@'%'; GRANT SELECT ON performance_schema.* TO 'chat'@'%'"
fi
figma_test_container=$(docker create --network "$figma_test_network" \
  -e XINSHI_CHAT_ISOLATED_TEST=1 -e XINSHI_CHAT_TEST_MYSQL="$figma_test_mysql" \
  -e CHAT_TEST_REPEATABLE_READ="${CHAT_TEST_REPEATABLE_READ:-0}" \
  --entrypoint sh "$CHAT_TEST_IMAGE" -c 'sleep 1800')
docker start "$figma_test_container" >/dev/null
docker exec "$figma_test_container" php -r 'file_put_contents(PHP_CONFIG_FILE_SCAN_DIR . "/99-figma-test.ini", "extension=pdo_sqlite\nextension=pdo_mysql\n");'
docker exec "$figma_test_container" mkdir -p /app/docroot/sites/default /app/docroot/modules/contrib
cd "$figma_test_root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/custom/xinshi_chat docroot/modules/custom/xinshi_ai_reference \
  docroot/modules/custom/xinshi_ai docroot/modules/contrib/ai docroot/modules/contrib/simple_oauth \
  docroot/modules/contrib/consumers docroot/modules/contrib/jsonapi_extras | docker cp - "$figma_test_container":/app
if [ -n "${CHAT_PHPUNIT_PHAR:-}" ]; then
  docker cp "$CHAT_PHPUNIT_PHAR" "$figma_test_container":/tmp/phpunit.phar
  docker exec "$figma_test_container" php /tmp/phpunit.phar --do-not-cache-result \
    --bootstrap /app/docroot/modules/custom/xinshi_ai/tests/bootstrap.php \
    /app/docroot/modules/custom/xinshi_ai/tests/src/Unit/FigmaAssetControllerTest.php
fi
docker exec "$figma_test_container" php /app/docroot/modules/custom/xinshi_ai/tests/figma-assets/integration.php
