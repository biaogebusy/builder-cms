#!/bin/sh
# Copy sources into a disposable container. Never mount an existing site or database.
set -eu
: "${CHAT_TEST_IMAGE:?Set a local PHP 8.3+ image with PDO SQLite}"
usage_upgrade_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../../.." && pwd)
docker image inspect "$CHAT_TEST_IMAGE" >/dev/null
usage_upgrade_container=$(docker create --network none --entrypoint sh "$CHAT_TEST_IMAGE" -c 'sleep 1800')
trap 'docker rm -f "$usage_upgrade_container" >/dev/null' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$usage_upgrade_container" >/dev/null
docker exec "$usage_upgrade_container" php -r 'file_put_contents(PHP_CONFIG_FILE_SCAN_DIR . "/99-usage-upgrade.ini", "extension=pdo_sqlite\n");'
docker exec "$usage_upgrade_container" mkdir -p /app/docroot/sites/default
cd "$usage_upgrade_root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/custom/xinshi_ai docroot/modules/custom/xinshi_ai_usage \
  docroot/modules/custom/xinshi_chat/tests/install-fixture.php | docker cp - "$usage_upgrade_container":/app
docker exec -e XINSHI_CHAT_ISOLATED_TEST=1 "$usage_upgrade_container" php \
  /app/docroot/modules/custom/xinshi_ai/tests/usage-upgrade/integration.php
