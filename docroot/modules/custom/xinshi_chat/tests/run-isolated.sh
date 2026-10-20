#!/bin/sh
# Sources are copied into an isolated disposable container; site mounts are never used.
set -eu
: "${CHAT_TEST_IMAGE:?Set a local PHP 8.3+ image with Drupal and SQLite extensions}"
root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../.." && pwd)
docker image inspect "$CHAT_TEST_IMAGE" >/dev/null
container=$(docker create --network none --entrypoint sh "$CHAT_TEST_IMAGE" -c 'sleep 1800')
trap 'docker rm -f "$container" >/dev/null' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$container" >/dev/null
docker exec "$container" php -r 'file_put_contents(PHP_CONFIG_FILE_SCAN_DIR . "/99-chat-test.ini", "extension=pdo_sqlite\n");'
docker exec "$container" mkdir -p /app/docroot/sites/default /app/docroot/modules/contrib
cd "$root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php docroot/core \
  docroot/modules/custom/xinshi_chat docroot/modules/custom/xinshi_ai_reference \
  docroot/modules/custom/xinshi_ai/src/Service/ChatSessionFieldScan.php \
  docroot/modules/custom/xinshi_ai/src/Service/ChatSessionFields.php \
  docroot/modules/custom/xinshi_ai/tests/bootstrap.php docroot/modules/custom/xinshi_ai/xinshi_ai.install \
  docroot/modules/custom/xinshi_ai/tests/src/Unit/ConversationWriterTest.php \
  docroot/modules/custom/xinshi_ai/tests/src/Unit/SessionContentWriterTest.php \
  docroot/modules/contrib/simple_oauth docroot/modules/contrib/consumers \
  docroot/modules/contrib/jsonapi_extras | docker cp - "$container":/app
docker exec "$container" sh -c \
  'find /app/docroot/modules/custom/xinshi_chat \( -name "*.php" -o -name "*.install" -o -name "*.module" \) -exec php -l {} \;'
if [ -n "${CHAT_PHPUNIT_PHAR:-}" ]; then
  docker cp "$CHAT_PHPUNIT_PHAR" "$container":/tmp/phpunit.phar
  docker exec "$container" php /tmp/phpunit.phar \
    --bootstrap /app/docroot/modules/custom/xinshi_ai/tests/bootstrap.php \
    /app/docroot/modules/custom/xinshi_ai/tests/src/Unit
fi
docker exec -e XINSHI_CHAT_ISOLATED_TEST=1 -e CHAT_TEST_SCENARIO="${CHAT_TEST_SCENARIO:-fresh}" "$container" php \
  /app/docroot/modules/custom/xinshi_chat/tests/integration.php
docker exec "$container" php /app/vendor/drush/drush/drush.php \
  --root=/app/docroot --uri=http://chat-test xinshi-chat:check
docker exec "$container" php /app/vendor/drush/drush/drush.php \
  --root=/app/docroot --uri=http://chat-test php:eval '\Drupal::keyValue("system.schema")->set("xinshi_chat", 11000);'
docker exec "$container" php /app/vendor/drush/drush/drush.php \
  --root=/app/docroot --uri=http://chat-test updatedb -y
docker exec "$container" php /app/vendor/drush/drush/drush.php \
  --root=/app/docroot --uri=http://chat-test xinshi-chat:sync
docker exec -e XINSHI_CHAT_ISOLATED_TEST=1 "$container" php \
  /app/docroot/modules/custom/xinshi_chat/tests/http.php
