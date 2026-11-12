#!/bin/sh
# Use fresh source copies and an internal Docker network for real row-lock contention.
set -eu
: "${CHAT_TEST_IMAGE:?Set a local PHP 8.3+ image with Drupal extensions and PDO MySQL}"
: "${CHAT_TEST_DB_IMAGE:?Set a local MySQL 8 or MariaDB image with MYSQL_DATABASE initialization}"
chat_test_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../.." && pwd)
docker image inspect "$CHAT_TEST_IMAGE" "$CHAT_TEST_DB_IMAGE" >/dev/null
chat_test_network=$(docker network create --internal "chat-mysql-$(date +%s)-$$")
chat_test_database=
chat_test_container=
cleanup() {
  if [ -n "$chat_test_container" ]; then docker rm -fv "$chat_test_container" >/dev/null; fi
  if [ -n "$chat_test_database" ]; then docker rm -fv "$chat_test_database" >/dev/null; fi
  docker network rm "$chat_test_network" >/dev/null
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
chat_test_database=$(docker create --memory 1g --cpus 2 \
  --network "$chat_test_network" --network-alias chat-db \
  -e MYSQL_ROOT_PASSWORD=chat-test-only -e MYSQL_DATABASE=chat_test \
  -e MYSQL_USER=chat -e MYSQL_PASSWORD=chat-test-only "$CHAT_TEST_DB_IMAGE")
docker start "$chat_test_database" >/dev/null
chat_test_admin=$(docker exec "$chat_test_database" sh -c 'command -v mariadb-admin || command -v mysqladmin')
chat_test_client=$(docker exec "$chat_test_database" sh -c 'command -v mariadb || command -v mysql')
chat_test_attempt=0
until docker exec "$chat_test_database" "$chat_test_admin" --protocol=TCP \
  -h127.0.0.1 ping -uroot -pchat-test-only --silent >/dev/null 2>&1; do
  chat_test_attempt=$((chat_test_attempt + 1))
  if [ "$chat_test_attempt" -ge 60 ]; then
    docker logs --tail 100 "$chat_test_database" >&2
    echo 'Database startup failed' >&2
    exit 1
  fi
  sleep 1
done
# These grants and the trigger setting apply to the disposable database only.
docker exec "$chat_test_database" "$chat_test_client" -uroot -pchat-test-only \
  -e "GRANT PROCESS ON *.* TO 'chat'@'%'; GRANT SELECT ON performance_schema.* TO 'chat'@'%'; SET GLOBAL log_bin_trust_function_creators=1"
chat_test_container=$(docker create --memory 512m --cpus 1 --network "$chat_test_network" \
  -e XINSHI_CHAT_ISOLATED_TEST=1 -e XINSHI_CHAT_TEST_MYSQL=1 \
  -e CHAT_TEST_REPEATABLE_READ="${CHAT_TEST_REPEATABLE_READ:-0}" \
  --entrypoint sh "$CHAT_TEST_IMAGE" -c 'sleep 1800')
docker start "$chat_test_container" >/dev/null
docker exec "$chat_test_container" mkdir -p /app/docroot/sites/default /app/docroot/modules/contrib
cd "$chat_test_root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/custom/xinshi_chat docroot/modules/custom/xinshi_ai_runtime docroot/modules/custom/xinshi_ai_reference \
  docroot/modules/contrib/simple_oauth docroot/modules/contrib/consumers \
  docroot/modules/contrib/jsonapi_extras | docker cp - "$chat_test_container":/app
docker exec "$chat_test_container" php -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_chat/tests/mysql-concurrency.php
