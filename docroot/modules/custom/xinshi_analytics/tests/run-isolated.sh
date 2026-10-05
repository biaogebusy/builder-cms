#!/bin/sh
# Run only in a disposable container; never mount application sites or credentials.
set -eu
: "${ANALYTICS_TEST_IMAGE:?Set an existing local PHP 8.3+ image with pdo_sqlite and Drupal extensions}"
root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../.." && pwd)
docker image inspect "$ANALYTICS_TEST_IMAGE" >/dev/null
container=$(docker create --network none --entrypoint sh "$ANALYTICS_TEST_IMAGE" -c 'sleep 1800')
trap 'docker rm -f "$container" >/dev/null' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
docker start "$container" >/dev/null
docker exec "$container" mkdir -p /app/docroot/sites/default /app/docroot/modules/contrib
# Copy only runtime dependencies and test sources; exclude all site configuration and files.
cd "$root"
tar -cf - vendor composer.json composer.lock docroot/autoload.php docroot/index.php \
  docroot/core docroot/modules/custom/xinshi_analytics \
  docroot/modules/contrib/simple_oauth docroot/modules/contrib/consumers | docker cp - "$container":/app
docker exec "$container" sh -c \
  'find /app/docroot/modules/custom/xinshi_analytics \( -name "*.php" -o -name "*.module" \) -exec php -l {} \;'
docker exec -e XINSHI_ANALYTICS_ISOLATED_TEST=1 "$container" php -d extension=pdo_sqlite \
  /app/docroot/modules/custom/xinshi_analytics/tests/integration.php
if [ -n "${ANALYTICS_PROTOCOL_OUTPUT:-}" ]; then
  docker cp "$container":/tmp/analytics-test/protocol.json "$ANALYTICS_PROTOCOL_OUTPUT"
fi
docker exec -e XINSHI_ANALYTICS_ISOLATED_TEST=1 "$container" php -d extension=pdo_sqlite \
  /app/docroot/modules/custom/xinshi_analytics/tests/http.php
if [ -n "${ANALYTICS_HTTP_PROTOCOL_OUTPUT:-}" ]; then
  docker cp "$container":/tmp/analytics-test/http-protocol.json "$ANALYTICS_HTTP_PROTOCOL_OUTPUT"
fi
if [ -n "${ANALYTICS_EVIDENCE_PROTOCOL_OUTPUT:-}" ]; then
  docker cp "$container":/tmp/analytics-test/evidence-protocol.json "$ANALYTICS_EVIDENCE_PROTOCOL_OUTPUT"
fi

# Optional cross-host conversation checks reuse only this disposable synthetic site.
if [ "${ANALYTICS_REFERENCE_TESTS:-0}" = 1 ]; then
  docker cp docroot/modules/custom/xinshi_ai_reference "$container":/app/docroot/modules/custom/
  docker exec "$container" mkdir -p /app/docroot/modules/custom/xinshi_ai/src/Service
  docker cp docroot/modules/custom/xinshi_ai/src/Service/ConversationWriter.php \
    "$container":/app/docroot/modules/custom/xinshi_ai/src/Service/
  docker exec "$container" sh -c \
    'find /app/docroot/modules/custom/xinshi_ai_reference \( -name "*.php" -o -name "*.module" \) -exec php -l {} \;'
  docker exec -e XINSHI_ANALYTICS_ISOLATED_TEST=1 "$container" php -d extension=pdo_sqlite \
    /app/docroot/modules/custom/xinshi_ai_reference/tests/integration.php
fi
