#!/usr/bin/env bash
set -euo pipefail

# The image must provide PHP 8.3+, required Drupal extensions, and SQLite modules.
: "${KNOWLEDGE_TEST_IMAGE:?Set KNOWLEDGE_TEST_IMAGE to the local PHP test image}"
repository_root="$(cd "$(dirname "$0")/../../../../.." && pwd)"
test_container="xinshi-knowledge-sync-check-$$"
test_network=""
cleanup() {
  docker rm -f "$test_container" >/dev/null 2>&1 || true
  if [[ -n "$test_network" ]]; then
    docker network rm "$test_network" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT
network_args=(--network none)
if [[ "${KNOWLEDGE_TEST_BROWSER:-}" == 1 ]]; then
  : "${KNOWLEDGE_BROWSER_MODULES:?Set KNOWLEDGE_BROWSER_MODULES to node_modules containing playwright and @axe-core/playwright}"
  test_network="$test_container"
  docker network create "$test_network" >/dev/null
  network_args=(--network "$test_network" -p 127.0.0.1::8080)
fi
docker run -d --name "$test_container" "${network_args[@]}" --entrypoint sh \
  "$KNOWLEDGE_TEST_IMAGE" -c 'sleep 1800' >/dev/null
docker exec "$test_container" mkdir -p /site/docroot/sites/default /tmp/xinshi-knowledge-test
# Only source and locked dependencies are copied; never copy sites, files or settings.
tar -C "$repository_root" -cf - vendor docroot/core docroot/modules/contrib \
  docroot/modules/custom composer.json composer.lock docroot/autoload.php docroot/autoload_runtime.php docroot/index.php |
  docker exec -i "$test_container" tar -xf - -C /site
if [[ -n "${KNOWLEDGE_TEST_SNAPSHOT:-}" ]]; then
  docker cp "$KNOWLEDGE_TEST_SNAPSHOT" "$test_container:/tmp/xinshi-docs-snapshot.json" >/dev/null
fi
docker exec -e "KNOWLEDGE_TEST_BROWSER=${KNOWLEDGE_TEST_BROWSER:-}" "$test_container" sh -eu -c '
  cp -R /site/docroot/modules/custom/xinshi_knowledge/tests/modules/xinshi_knowledge_test_access /site/docroot/modules/custom/
  cp -R /site/docroot/modules/custom/xinshi_knowledge_sync/tests/modules/xinshi_knowledge_sync_test /site/docroot/modules/custom/
  if ! php -r "exit(extension_loaded(\"pdo_sqlite\") && extension_loaded(\"sqlite3\") ? 0 : 1);"; then
    export PHP_INI_SCAN_DIR="$(php -r "echo PHP_CONFIG_FILE_SCAN_DIR;"):/tmp/sync-php-ini"
    mkdir /tmp/sync-php-ini
    printf "extension=pdo_sqlite\nextension=sqlite3\n" > /tmp/sync-php-ini/sqlite.ini
  fi
  export XINSHI_KNOWLEDGE_ISOLATED_TEST=1
  php /site/docroot/modules/custom/xinshi_knowledge_sync/tests/integration.php
  cd /site
  vendor/bin/drush help xinshi-knowledge:sync
  if [ -f /tmp/xinshi-docs-snapshot.json ]; then
    vendor/bin/drush xinshi-knowledge:sync /tmp/xinshi-docs-snapshot.json --source=xinshi-docs --account=1
    vendor/bin/drush xinshi-knowledge:sync /tmp/xinshi-docs-snapshot.json --source=xinshi-docs --account=1 --apply
  fi
  if [ "$KNOWLEDGE_TEST_BROWSER" = 1 ]; then
    vendor/bin/drush php:script docroot/modules/custom/xinshi_knowledge_sync/tests/browser-fixture.php
  fi
'
if [[ "${KNOWLEDGE_TEST_BROWSER:-}" == 1 ]]; then
  docker exec -d -w /site/docroot "$test_container" sh -eu -c '
    export PHP_INI_SCAN_DIR="$(php -r "echo PHP_CONFIG_FILE_SCAN_DIR;"):/tmp/sync-php-ini"
    cp core/assets/scaffold/files/ht.router.php .ht.router.php
    exec php -S 0.0.0.0:8080 .ht.router.php > /tmp/xinshi-knowledge-test/browser-http.log 2>&1
  '
  export KNOWLEDGE_UI_URL="http://$(docker port "$test_container" 8080/tcp)"
  export KNOWLEDGE_UI_FIXTURE="$(docker exec "$test_container" cat /tmp/xinshi-knowledge-test/form-fixture.json)"
  node "$repository_root/docroot/modules/custom/xinshi_knowledge_sync/tests/admin-ui.mjs"
fi
