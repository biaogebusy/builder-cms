#!/bin/sh
# Copy sources into a disposable container; no existing sites or databases are mounted.
set -eu
: "${CHAT_TEST_IMAGE:?Set a local PHP 8.3+ image with PDO SQLite}"
repair_test_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd)
docker image inspect "$CHAT_TEST_IMAGE" >/dev/null
repair_test_container=$(docker create --network none --entrypoint sh "$CHAT_TEST_IMAGE" -c 'sleep 1800')
repair_test_database=''
repair_test_mysql=0
repair_test_cleanup() {
  if [ -n "$repair_test_database" ]; then docker rm -fv "$repair_test_database" >/dev/null; fi
  docker rm -fv "$repair_test_container" >/dev/null
}
trap repair_test_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$repair_test_container" >/dev/null
docker exec "$repair_test_container" php -r 'file_put_contents(PHP_CONFIG_FILE_SCAN_DIR . "/99-repair-test.ini", "extension=pdo_sqlite\nextension=pdo_mysql\n");'
if [ -n "${REPAIR_TEST_MARIADB_IMAGE:-}" ]; then
  docker image inspect "$REPAIR_TEST_MARIADB_IMAGE" >/dev/null
  repair_test_mysql=1
  repair_test_database=$(docker run -d --network "container:$repair_test_container" \
    -e MYSQL_ROOT_PASSWORD=isolated-root -e MYSQL_DATABASE=chat_test \
    -e MYSQL_USER=chat -e MYSQL_PASSWORD=chat-test-only "$REPAIR_TEST_MARIADB_IMAGE")
  docker exec "$repair_test_container" sh -c 'printf "127.0.0.1 chat-db\n" >> /etc/hosts'
  docker exec "$repair_test_container" php -r '
    for ($i = 0; $i < 60; $i++) {
      try { new PDO("mysql:host=127.0.0.1;dbname=chat_test", "chat", "chat-test-only"); exit(0); }
      catch (Throwable $error) { usleep(500000); }
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1);'
fi
docker exec "$repair_test_container" mkdir -p /app/docroot/sites/default
cd "$repair_test_root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/custom/xinshi_ai docroot/modules/custom/xinshi_ai_usage \
  docroot/modules/custom/xinshi_chat/tests/install-fixture.php \
  scripts/repair-retired-layout-metadata.php scripts/tests/retired-layout | docker cp - "$repair_test_container":/app
docker exec "$repair_test_container" php -l /app/scripts/repair-retired-layout-metadata.php
docker exec "$repair_test_container" php -l /app/scripts/tests/retired-layout/integration.php
docker exec -e XINSHI_CHAT_ISOLATED_TEST=1 -e XINSHI_CHAT_TEST_MYSQL="$repair_test_mysql" \
  "$repair_test_container" php /app/scripts/tests/retired-layout/integration.php
