#!/usr/bin/env bash
set -euo pipefail

if (( $# > 2 )); then
  echo 'Usage: bash scripts/migration/docker-local.sh STEP [PRIVATE_BATCH_DIRECTORY_IN_CONTAINER]' >&2
  exit 2
fi

step="${1:-help}"
case "$step" in
  help|status|preflight|backup|install-modules|adapt-block-body|install-config|map-config-users|validate-model|clear-generated-rows|import|verify|verify-entities|verify-views|verify-access) ;;
  *) echo 'Unknown migration step.' >&2; exit 2 ;;
esac

migration_container="${BUILDER_MIGRATION_CONTAINER:-builder-pro}"
migration_root="${BUILDER_MIGRATION_APP_ROOT:-/var/www/html}"
migration_origin="${BUILDER_MIGRATION_ORIGIN:-http://builder-pro.docker.localhost}"
migration_batch="${2:-${migration_root}/.migration-private/batch}"

docker_options=(exec --user "${BUILDER_MIGRATION_EXEC_USER:-0}" --workdir "$migration_root")
if [[ "$step" != help ]]; then
  docker_options+=(--env XINSHI_MIGRATION_RUN=1)
  if [[ -n "${BUILDER_MIGRATION_SOURCE_ENV_FILE:-}" ]]; then
    if [[ ! -f "$BUILDER_MIGRATION_SOURCE_ENV_FILE" || ! -r "$BUILDER_MIGRATION_SOURCE_ENV_FILE" ]]; then
      echo 'The private source environment file is not readable.' >&2
      exit 2
    fi
    # Older Docker clients lack exec --env-file. Export only the source keys,
    # without evaluating shell text or putting their values in process args.
    while IFS= read -r migration_setting || [[ -n "$migration_setting" ]]; do
      migration_setting="${migration_setting%$'\r'}"
      [[ -z "$migration_setting" || "$migration_setting" == \#* ]] && continue
      case "$migration_setting" in
        XINSHI_MIGRATION_SOURCE_DATABASE=*|XINSHI_MIGRATION_SOURCE_USERNAME=*|XINSHI_MIGRATION_SOURCE_PASSWORD=*|XINSHI_MIGRATION_SOURCE_HOST=*|XINSHI_MIGRATION_SOURCE_PORT=*)
          export "$migration_setting"
          docker_options+=(--env "${migration_setting%%=*}")
          ;;
        *) echo 'Unexpected entry in the private source environment file.' >&2; exit 2 ;;
      esac
    done < "$BUILDER_MIGRATION_SOURCE_ENV_FILE"
    unset migration_setting
  fi
fi

exec docker "${docker_options[@]}" "$migration_container" \
  php -d memory_limit=1024M \
  "$migration_root/vendor/drush/drush/drush.php" \
  "--root=$migration_root/docroot" "--uri=$migration_origin" \
  php:script "$migration_root/scripts/migration/run.php" -- "$step" "$migration_batch"
