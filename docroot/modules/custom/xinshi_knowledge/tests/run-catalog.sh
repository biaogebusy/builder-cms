#!/usr/bin/env bash
set -euo pipefail
: "${KNOWLEDGE_TEST_IMAGE:?Set KNOWLEDGE_TEST_IMAGE to the local PHP test image}"
: "${KNOWLEDGE_BROWSER_MODULES:?Set KNOWLEDGE_BROWSER_MODULES to node_modules containing playwright}"
repository_root="$(cd "$(dirname "$0")/../../../../.." && pwd)"
test_container="xinshi-knowledge-catalog-check-$$"
cleanup() { docker rm -f "$test_container" >/dev/null 2>&1 || true; }
trap cleanup EXIT
docker run -d --name "$test_container" -p 127.0.0.1::8080 --entrypoint sh \
  "$KNOWLEDGE_TEST_IMAGE" -c 'sleep 1800' >/dev/null
docker exec "$test_container" mkdir -p /site/docroot/sites/default /tmp/xinshi-knowledge-test
tar -C "$repository_root" -cf - vendor docroot/core docroot/modules/contrib \
  docroot/modules/custom composer.json composer.lock docroot/autoload.php docroot/autoload_runtime.php docroot/index.php |
  docker exec -i "$test_container" tar -xf - -C /site
docker exec "$test_container" sh -eu -c '
  cp -R /site/docroot/modules/custom/xinshi_knowledge/tests/modules/xinshi_knowledge_test_access /site/docroot/modules/custom/
  mkdir /tmp/catalog-php-ini
  printf "extension=pdo_sqlite\nextension=sqlite3\n" > /tmp/catalog-php-ini/sqlite.ini
  export PHP_INI_SCAN_DIR="$(php -r "echo PHP_CONFIG_FILE_SCAN_DIR;"):/tmp/catalog-php-ini"
  export XINSHI_KNOWLEDGE_ISOLATED_TEST=1
  php /site/docroot/modules/custom/xinshi_knowledge/tests/catalog.integration.php
  cd /site
  vendor/bin/drush cache:rebuild
'
docker exec -d -w /site/docroot "$test_container" sh -eu -c '
  export PHP_INI_SCAN_DIR="$(php -r "echo PHP_CONFIG_FILE_SCAN_DIR;"):/tmp/catalog-php-ini"
  cp core/assets/scaffold/files/ht.router.php .ht.router.php
  exec php -S 0.0.0.0:8080 .ht.router.php > /tmp/xinshi-knowledge-test/http.log 2>&1
'
export KNOWLEDGE_UI_URL="http://$(docker port "$test_container" 8080/tcp)"
export KNOWLEDGE_UI_FIXTURE="$(docker exec "$test_container" cat /tmp/xinshi-knowledge-test/catalog-fixture.json)"
node "$repository_root/docroot/modules/custom/xinshi_knowledge/tests/catalog-http.mjs"
