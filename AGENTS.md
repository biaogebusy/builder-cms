# AGENTS.md

Guidance for coding agents working in Builder CMS. `CLAUDE.md` imports this file;
maintain shared project rules here. Adapted on 2026-09-30 from the sibling
`xinshi-pro/AGENTS.md`, `xinshi-pro/CLAUDE.md`, and this project's existing guidance.

## Project and repository boundaries

- This repository is the active Drupal 11 CMS development target. Read
  `composer.json` and `composer.lock` for actual dependencies and versions.
  Custom modules live in `docroot/modules/custom/`; this is not an Angular app.
- `../xinshi-base` is the source of truth for ordinary frontend behavior.
  `../xinshi-pro` is the source of truth for AI behavior and Node/Harness integration.
  Before implementing a change whose behavior is unclear, read the relevant caller
  in the corresponding repository to confirm the existing logic. Check its actual
  branch, proxy, environment and protocol code; do not infer frontend behavior from
  CMS code alone or substitute the Pro implementation for ordinary frontend logic.
  Shared frontend changes are made only in `../xinshi-base`; they reach
  `../xinshi-pro` through a later base-branch merge, not direct copying. Keep
  AI-specific integration in Pro separate and record any pending base dependency.
- `../pro` is a frontend deployment checkout. `../xinshi-cms` is the base CMS in
  delivery/maintenance state. Do not automatically propagate changes to either.
- Inspect the current branch and working tree before editing. Frontend `master`
  and AI branch rules do not define this CMS repository's branch strategy.
- Keep optional functionality in its own module with explicit dependencies and
  small integration points. Shared CMS behavior must also work with optional AI
  modules disabled. Do not copy Angular, Tailwind or Node tool-kernel rules here.

## Documentation and progress are part of delivery

The single maintenance location for architecture, APIs, operations, review reports
and implementation progress is `../xinshi-docs/stories/`. Keep source READMEs as
entry points, not duplicate specifications.

- [Documentation index](../xinshi-docs/stories/develop/engineering/documentation.mdx)
- [CMS custom module review](../xinshi-docs/stories/develop/engineering/cms-custom-modules-review.mdx)
- [CMS remediation progress and change history](../xinshi-docs/stories/develop/engineering/cms-custom-modules-progress.mdx)
- [Permission rules and compatibility](../xinshi-docs/stories/develop/engineering/permissions.mdx)

For every subsequent change to reviewed CMS behavior:

1. Read the relevant report item and progress row before implementation. Preserve
   stable `CMS-Rxx` finding IDs and `CMS-Mxx` maintenance IDs; add new IDs for new work.
2. Update the corresponding feature/API/operations MDX when implementation changes.
   If evidence changes a review conclusion, amend the report with a dated correction.
3. Update the progress row and append a dated change record in the same delivery:
   scope, source paths or commit/PR when available, validation performed, result,
   remaining limitations and next action. Do not invent commit IDs or test results.
4. Keep planned, implemented, verified and deployed states distinct. Documentation
   creation and syntax checks alone do not close a defect. Record blocked checks
   and environment prerequisites rather than marking the item complete.
5. Summarize code and documentation changes together. If documentation could not be
   updated, report the specific outstanding work; do not claim full completion.
6. Before changing authorization, read the permission rules linked above. Document
   new, changed or retired permission rules there in the same delivery, including
   their scope, Drupal configuration, existing-site compatibility and verification.
   Add future permission domains to that page and link their detailed API docs.

Internal documentation links use actual Storybook routes derived from `Meta.title`.
Follow the documentation repository's own instructions for MDX, index generation
and builds. Documentation-only changes do not require CMS application tests.

## Local environment and commands

`docker/docker-compose.yml` and `docroot/sites/default/settings.php` define the
environment. See `README.md` for local URLs; never infer production addresses or
copy credentials into code, comments, logs or documentation.

| Environment | Compose service/container | Config directory |
| --- | --- | --- |
| base | `builder-base` | `config/base/sync` |
| Pro | `builder-pro` | `config/pro/sync` |

Both services mount this checkout at `/var/www/html` and use PHP 8.3 images. They
have separate databases and module configurations; the shared uploaded-file mount
is described in `README.md`. Verify the target service and mount before execution.

Read-only diagnostic examples, after checking the matching container is running:

```sh
git status --short
docker ps --format '{{.Names}}\t{{.Image}}'
docker exec -w /var/www/html builder-base vendor/bin/drush status
docker exec -w /var/www/html builder-pro vendor/bin/drush status
docker exec -w /var/www/html builder-base php -l docroot/modules/custom/xinshi_api/xinshi_api.module
```

Do not assume PHPUnit, PHPCS or PHPStan are installed: the initial review found no
root `require-dev` or unified test scripts. Use the available, documented runner
and report missing tools. Do not run a placeholder `npm test` as CMS validation.
Database imports, migration/cleanup commands, configuration imports, module
uninstalls and production operations require an explicitly authorized target and
scope; a source review does not authorize these operations.

## Drupal implementation rules

- Keep controllers thin. Put reusable business logic in services declared in
  `*.services.yml`; use constructor injection and container factories. Procedural
  hooks may resolve services at the boundary. Avoid broad rewrites solely to remove
  existing `\Drupal::` calls.
- Declare direct module dependencies in `*.info.yml`. Keep optional dependencies
  optional throughout service construction and execution. Follow the locked Drupal
  APIs; do not claim compatibility with a core version without checking it.
- Use Drupal coding conventions, PHPDoc and precise types compatible with parent
  signatures. Write new explanatory code comments in English; do not translate
  unrelated legacy comments. User-facing labels use Drupal translation APIs.
- Validate request shape, types, allowed fields, sizes and semantic constraints
  before side effects. Return meaningful HTTP status codes and stable errors;
  log diagnostic detail without exposing secrets or raw internal exceptions.
- Route permissions are the first boundary. Check entity, field, referenced-entity,
  translation, text-format and moderation-transition access where relevant.
  Entity loading and `save()` do not perform these checks for custom callers.
  A supplied UUID does not prove ownership or authorization.
- Preserve the explicitly agreed legacy Builder page contract: landing-page
  create/update/delete authority comes from Drupal page permissions. Its JSON
  components are written under that page authority, including cross-page UUID
  sharing; do not add block-library or user text-format grants to that protocol.
  Keep the fixed internal `format: json` marker compatible with existing sites.
  This exception does not grant access to generic block or unrelated API routes.
- State-changing routes accepting session cookies require CSRF protection. OAuth-only
  routes must explicitly enforce that authentication mode. Distinguish user OAuth
  from service-to-service signatures; neither replaces business authorization.
- Login must reject blocked users. Validate OTP types before comparison, use secure
  randomness, apply send and verification limits at the shared service boundary,
  and consume challenges once. Bind OAuth state to the initiating browser and expire
  and consume it once. Prefer supported OAuth grant/token infrastructure.
- Remote URLs, redirects and provider-returned asset URLs are untrusted. Restrict
  schemes and network destinations, bound time and bytes, and validate file contents.
  Check access to private input files using the submitting user before sending them
  to another service. Store secrets outside exportable configuration and entities.
- Propagate cache tags, contexts and max-age from entities, access results and render
  output. Cache identifiers must distinguish relevant language, revision and user
  variants. Do not use permanent caching for personalized or unpublished data.
- Use transactions for related writes and explicit revisions for history. Preserve
  idempotency, concurrent-edit detection, queue leases, cancellation and reconciliation.
  Queue workers must recheck relevant authorization and state, not trust stale input.
- Avoid unbounded queries and per-row entity loads; use bounded batches/loadMultiple
  and appropriate indexes. Explain intentional `accessCheck(FALSE)` at internal or
  administrative boundaries. Use bound database parameters and Drupal storage APIs.
- Define configuration schema and defaults as needed. Existing sites need idempotent
  update hooks when install-time schema/configuration changes; do not overwrite
  deployment-specific settings or assume base and Pro configurations are identical.
- For administrative UI, retain Form API validation, escaping, labels, keyboard
  operation and accessible status/error feedback.

## Cross-repository contracts

Check ordinary frontend callers in `../xinshi-base` and AI/Node callers in
`../xinshi-pro` before changing `/api/v3`, JSON:API, authentication,
error codes, page revisions, AI job events or usage/metering contracts. Keep CMS
business policy in CMS/product adapters; do not move it into the generic Harness
tool kernel. Changes affecting submitted writes must preserve reconciliation and
document incompatible behavior. Run relevant sibling checks only when that code or
contract is affected, using that repository's current instructions and commands.

## Configured analytics boundary

- `xinshi_analytics` is an optional, count-only source module. Keep entity and field
  mappings, exact translation/publication rules and access checks there; do not
  add business branches to the Node Harness kernel.
- Datasets are explicit versioned configuration entities. Querying needs the
  general query permission plus the dataset-specific grant and actual entity/field
  access. Configuration administration is a separate permission. No default
  datasets, grants or automatic site updates are installed. Fixed authenticated
  capabilities/datasets/count endpoints are read-only and always private/no-store,
  including authentication and access failures. Keep OAuth scope in the effective
  principal throughout dataset, query, entity and field checks; never replace it
  with the full user. POST count does not mutate data or require write CSRF.
- Preserve access-checked ID pagination, complete results and configured scan/group
  limits. Do not push private field predicates into SQL before proving equivalent
  access semantics. `max_seconds` is cooperative, not a database statement timeout.
- Historical evidence uses the user-confirmed conservative invalidation rule.
  Keep original counts immutable. Revalidate current access, participating records
  and field values, plus durable bundle/account/configuration markers; equal totals
  alone cannot authorize old evidence. Seal receipts with the purpose-separated
  site secret and never expose internal fingerprints or record IDs.
- Configuration saves and source/entity access changes invalidate evidence even
  when values or grants are restored. Project rules outside the covered hooks must
  call the evidence epoch invalidation service. Do not use expiring cache tags as
  durable history, or claim polling can detect an unannounced change later reversed.
  Receipt capture/verification is read-only; mutation hooks update markers, not reports.
- Run the module's isolated integration entry for configuration/count/evidence changes.
  It copies sources into a disposable network-none container and uses synthetic
  SQLite data. Never run its installation script in an existing site. Protocol,
  Cookie/OAuth permissions, validation and pending workflow integration are maintained in
  [Cross-business Plan](../xinshi-docs/stories/pro/ai/architecture/cross-business-plan.mdx).

## Protected conversation references

- `xinshi_ai_reference` is an optional session-storage guard, independent of the
  analytics source and heavy AI module dependencies. Keep report bodies, receipts,
  reasoning, summaries and generated report titles out of ordinary ai_session fields.
- The fixed versioned runId/chatId locator is untrusted. Node validates task ownership,
  original conversation and current source evidence before first-party save/recovery;
  Drupal validates shape, author and conversation binding, not Node task existence.
  Never describe the locator as a capability or an independent historical-report grant.
- Use entity validation plus presave for copy-free fields and immutable binding,
  covering ConversationWriter and direct JSON:API writes. Node access alone does not
  protect JSON:API relationship linkage: retain the sessions field access check too.
  Keep unrelated node/field access neutral and preserve existing entity permissions.
- Run `ANALYTICS_REFERENCE_TESTS=1` with the analytics isolated runner for changes.
  The optional fixture also uses actual JSON:API and field-based Views; no production
  dependency between the two modules is implied. Real site aliases, fields, Views and
  other writers still need deployment verification. No automatic migration can identify
  sensitive report numbers hidden in previously saved ordinary text.

## Working method and verification

- State material assumptions and acceptance criteria. Proceed with routine choices
  within the authorized scope; ask only when missing information materially affects
  correctness, behavior or data safety.
- Make focused changes, preserve unrelated working-tree edits, and avoid speculative
  abstractions or unrelated formatting. Remove only unused code introduced by the task.
- Choose validation for the risk: syntax for PHP edits, focused unit tests for logic,
  Kernel/Functional tests for Drupal access, configuration, persistence and routes.
  Security fixes need the original failure case and legitimate-use regressions.
- Exercise relevant anonymous/authenticated, owner/non-owner, blocked-user, cookie/
  OAuth, language/revision and optional-module cases. Do not equate mocked checks,
  syntax parsing or a green subset with a full application acceptance.
- Record exact commands, environment, tested scope and failures. Separate historical
  review evidence from newly executed checks. Update the linked documents and progress
  before reporting the task as complete.
