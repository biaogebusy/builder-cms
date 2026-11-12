#!/bin/sh
# Compile real module definitions against synthetic configuration and SQLite only.
set -eu
finance_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../../../.." && pwd)
finance_fixture=$(mktemp -d "${TMPDIR:-/tmp}/xinshi-finance-XXXXXX")
trap 'rm -rf "$finance_fixture"' EXIT
mkdir -p "$finance_fixture/docroot/modules/custom" "$finance_fixture/config"
touch "$finance_fixture/.isolated-finance-fixture"
ln -s "$finance_root/vendor" "$finance_fixture/vendor"
ln -s "$finance_root/docroot/core" "$finance_fixture/docroot/core"
for finance_module in xinshi_credits xinshi_rewards xinshi_ai_billing xinshi_ai_usage xinshi_ai xinshi_ai_runtime; do
  cp -R "$finance_root/docroot/modules/custom/$finance_module" "$finance_fixture/docroot/modules/custom/"
done
XINSHI_FINANCE_ISOLATED_TEST=1 "${FINANCE_PHP_BIN:-php}" \
  "$finance_fixture/docroot/modules/custom/xinshi_credits/tests/finance-assembly.php"
