# Xinshi Analytics

Opt-in Drupal services and authenticated read-only endpoints for configured entity
counts. No default datasets, grants or automatic site updates are installed.

Architecture, configuration and verification are maintained in
[Cross-business Plan and Drupal analytics](../../../../../xinshi-docs/stories/pro/ai/architecture/cross-business-plan.mdx).

`tests/run-isolated.sh` installs a disposable SQLite Drupal site and runs service
and Cookie/OAuth HTTP checks. Run only using
the isolated container procedure documented there, never against a site checkout.

