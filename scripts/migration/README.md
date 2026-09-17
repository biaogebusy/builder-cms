# Xinshi selective snapshot migration

Entry point: `vendor/bin/drush php:script "$PWD/scripts/migration/run.php" -- help`.

The runner uses the target site's default database connection and a SELECT-only
source connection named by the private snapshot plan (normally `migrate`).
Each invocation runs one explicit step. The private batch directory must be
outside the public docroot; it contains the reviewed plans, runtime settings,
recovery points, execution state and private diagnostics.

The maintained scope, setup, command sequence, validation results and recovery
instructions are in xinshi-docs: [Builder CMS 内容迁移](https://ui.builder.design/?path=/docs/部署-工具-builder-cms-内容迁移--docs).

These scripts apply the reviewed snapshot and selection. They do not automatically
recalculate scope for a different database export.

For `builder-pro.docker.localhost`, use
`bash scripts/migration/docker-local.sh help` from the host repository root.
The wrapper selects the `builder-pro` container and local URI, and can pass a
private source env-file for CLI use. See [Docker local migration](DOCKER_LOCAL.md)
and `batch-runtime.docker-local.example.json` for setup, migration order,
file verification, and exporting the validated local database for deployment.

The local Docker `builder` migration completed on 2026-09-16 with its own
baseline backup and execution state. All 179 selected tables / 365,718 rows,
entity revisions, 52 retained Views, 32 edit forms and access checks passed.
File records and references were verified locally; file bodies remain online
as requested and were not downloaded.

`export-docker-local.py` exports the verified local database, requires the five
successful verification reports, validates the target identity and input hashes,
and omits runtime table contents such as caches, sessions and temporary tokens.
The completed SQL archive and separate code/private input packages are recorded
in the local migration completion report. No online database was modified.

For the later online import, use `settings.deployment.example.php` with the
server's existing production OAuth signing pair. Local generated keys and source
connection files are excluded from the delivery packages.

Local HTTP checks used the configured hostname with explicit 127.0.0.1 resolution.
The host system resolver currently returns 28.0.0.59; see DOCKER_LOCAL.md for the
curl command and the separate DNS observation.
