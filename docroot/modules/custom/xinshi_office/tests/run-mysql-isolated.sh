#!/bin/sh
# Use fresh source copies and an internal Docker network for real row-lock contention.
set -eu
: "${OFFICE_TEST_IMAGE:?Set a local PHP 8.3+ image with Drupal extensions and PDO MySQL}"
: "${OFFICE_TEST_DB_IMAGE:?Set a local MySQL 8 or MariaDB image with MYSQL_DATABASE initialization}"
office_test_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../.." && pwd)
docker image inspect "$OFFICE_TEST_IMAGE" "$OFFICE_TEST_DB_IMAGE" >/dev/null
office_test_network=$(docker network create --internal "office-mysql-$(date +%s)-$$")
office_test_database=
office_test_container=
cleanup() {
  if [ -n "$office_test_container" ]; then docker rm -fv "$office_test_container" >/dev/null; fi
  if [ -n "$office_test_database" ]; then docker rm -fv "$office_test_database" >/dev/null; fi
  docker network rm "$office_test_network" >/dev/null
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
office_test_database=$(docker create --memory 512m --cpus 1 \
  --network "$office_test_network" --network-alias office-db \
  -e MYSQL_ROOT_PASSWORD=office-test-only -e MYSQL_DATABASE=office_test \
  -e MYSQL_USER=office -e MYSQL_PASSWORD=office-test-only "$OFFICE_TEST_DB_IMAGE")
docker start "$office_test_database" >/dev/null
office_test_admin=$(docker exec "$office_test_database" sh -c 'command -v mariadb-admin || command -v mysqladmin')
office_test_client=$(docker exec "$office_test_database" sh -c 'command -v mariadb || command -v mysql')
office_test_attempt=0
until docker exec "$office_test_database" "$office_test_admin" --protocol=TCP \
  -h127.0.0.1 ping -uroot -poffice-test-only --silent >/dev/null 2>&1; do
  office_test_attempt=$((office_test_attempt + 1))
  if [ "$office_test_attempt" -ge 60 ]; then echo 'Database startup failed' >&2; exit 1; fi
  sleep 1
done
# These grants and the trigger setting apply to the disposable database only.
docker exec "$office_test_database" "$office_test_client" -uroot -poffice-test-only \
  -e "GRANT PROCESS ON *.* TO 'office'@'%'; GRANT SELECT ON performance_schema.* TO 'office'@'%'; SET GLOBAL log_bin_trust_function_creators=1"
office_test_container=$(docker create --memory 512m --cpus 1 --network "$office_test_network" \
  -e XINSHI_OFFICE_ISOLATED_TEST=1 -e XINSHI_OFFICE_TEST_MYSQL=1 \
  --entrypoint sh "$OFFICE_TEST_IMAGE" -c 'sleep 1800')
docker start "$office_test_container" >/dev/null
docker exec "$office_test_container" mkdir -p /app/docroot/sites/default
cd "$office_test_root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/custom/xinshi_office | docker cp - "$office_test_container":/app
docker exec "$office_test_container" php -d extension=pdo_mysql \
  /app/docroot/modules/custom/xinshi_office/tests/mysql-concurrency.php
