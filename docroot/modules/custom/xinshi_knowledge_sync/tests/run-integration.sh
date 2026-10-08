#!/usr/bin/env bash
set -euo pipefail

# The image must provide PHP 8.3+, required Drupal extensions, and SQLite modules.
: "${KNOWLEDGE_TEST_IMAGE:?Set KNOWLEDGE_TEST_IMAGE to the local PHP test image}"
repository_root="$(cd "$(dirname "$0")/../../../../.." && pwd)"
test_container="xinshi-knowledge-sync-check-$$"
trap 'docker rm -f "$test_container" >/dev/null 2>&1 || true' EXIT
docker run -d --name "$test_container" --network none --entrypoint sh \
  "$KNOWLEDGE_TEST_IMAGE" -c 'sleep 600' >/dev/null
docker exec "$test_container" mkdir -p /site/docroot/sites/default /tmp/xinshi-knowledge-test
# Only source and locked dependencies are copied; never copy sites, files or settings.
tar -C "$repository_root" -cf - vendor docroot/core docroot/modules/contrib \
  docroot/modules/custom composer.json composer.lock docroot/autoload.php docroot/index.php |
  docker exec -i "$test_container" tar -xf - -C /site
if [[ -n "${KNOWLEDGE_TEST_SNAPSHOT:-}" ]]; then
  docker cp "$KNOWLEDGE_TEST_SNAPSHOT" "$test_container:/tmp/xinshi-docs-snapshot.json" >/dev/null
fi
docker exec "$test_container" sh -eu -c '
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
'
