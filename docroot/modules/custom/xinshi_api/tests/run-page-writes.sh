#!/bin/sh
# Copy source into a disposable, network-isolated Drupal/SQLite site.
set -eu
cd "$(dirname "$0")/../../../../.."
page_test_output=$(mktemp -d "${TMPDIR:-/tmp}/xinshi-page-writes.XXXXXX")
page_test_container=$(docker create --network none --entrypoint sh \
  registry.cn-hangzhou.aliyuncs.com/davyin/nginx-php:8.3-alpine -c 'sleep 1800')
cleanup() {
  docker cp "$page_test_container":/tmp/xinshi-api-test/auth-http-server.log \
    "$page_test_output/http-server.log" >/dev/null 2>&1 || true
  docker rm -fv "$page_test_container" >/dev/null
  printf 'Page test logs: %s\n' "$page_test_output"
}
trap cleanup EXIT
docker start "$page_test_container" >/dev/null
docker exec "$page_test_container" php -r \
  'file_put_contents(PHP_CONFIG_FILE_SCAN_DIR . "/99-page-test.ini", "extension=pdo_sqlite\n");'
docker exec "$page_test_container" mkdir -p /app/docroot/sites/default
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php \
  docroot/core docroot/modules/contrib docroot/modules/custom | docker cp - "$page_test_container":/app
for page_test in integration-smoke page-write-smoke auth-http-smoke; do
  if ! docker exec -e XINSHI_API_ISOLATED_TEST=1 "$page_test_container" \
    php "/app/docroot/modules/custom/xinshi_api/tests/$page_test.php" \
    > "$page_test_output/$page_test.log" 2>&1; then
    cat "$page_test_output/$page_test.log"
    exit 1
  fi
  cat "$page_test_output/$page_test.log"
done
