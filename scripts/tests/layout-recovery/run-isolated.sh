#!/bin/sh
# Use a disposable source copy, never an existing site or database mount.
set -eu
: "${CHAT_TEST_IMAGE:?Set a local PHP 8.3+ image with PDO SQLite}"
layout_test_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd)
docker image inspect "$CHAT_TEST_IMAGE" >/dev/null
layout_test_container=$(docker create --network none --entrypoint sh "$CHAT_TEST_IMAGE" -c 'sleep 1800')
layout_test_database=''
layout_test_mysql=0
layout_test_cleanup() {
  if [ -n "$layout_test_database" ]; then docker rm -fv "$layout_test_database" >/dev/null; fi
  docker rm -fv "$layout_test_container" >/dev/null
}
trap layout_test_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$layout_test_container" >/dev/null
docker exec "$layout_test_container" php -r 'file_put_contents(PHP_CONFIG_FILE_SCAN_DIR . "/99-layout-test.ini", "extension=pdo_sqlite\nextension=pdo_mysql\n");'
if [ -n "${REPAIR_TEST_MARIADB_IMAGE:-}" ]; then
  docker image inspect "$REPAIR_TEST_MARIADB_IMAGE" >/dev/null
  layout_test_mysql=1
  layout_test_database=$(docker run -d --network "container:$layout_test_container" \
    -e MYSQL_ROOT_PASSWORD=isolated-root -e MYSQL_DATABASE=chat_test \
    -e MYSQL_USER=chat -e MYSQL_PASSWORD=chat-test-only "$REPAIR_TEST_MARIADB_IMAGE")
  docker exec "$layout_test_container" sh -c 'printf "127.0.0.1 chat-db\n" >> /etc/hosts'
  docker exec "$layout_test_container" php -r '
    for ($i = 0; $i < 60; $i++) {
      try { new PDO("mysql:host=127.0.0.1;dbname=chat_test", "chat", "chat-test-only"); exit(0); }
      catch (Throwable $error) { usleep(500000); }
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1);'
fi
docker exec "$layout_test_container" mkdir -p /app/docroot/sites/default
cd "$layout_test_root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/contrib/layout_builder_at docroot/modules/custom/xinshi_editor \
  docroot/modules/custom/xinshi_chat/tests/install-fixture.php \
  scripts/layout-recovery scripts/restore-panelizer-layouts.php scripts/tests/layout-recovery \
  | docker cp - "$layout_test_container":/app
docker exec "$layout_test_container" sh -c 'find /app/scripts/layout-recovery /app/scripts/tests/layout-recovery -name "*.php" -exec php -l {} \;'
docker exec "$layout_test_container" php -l /app/scripts/restore-panelizer-layouts.php
docker exec -e XINSHI_CHAT_ISOLATED_TEST=1 -e XINSHI_CHAT_TEST_MYSQL="$layout_test_mysql" \
  "$layout_test_container" php /app/scripts/tests/layout-recovery/integration.php
