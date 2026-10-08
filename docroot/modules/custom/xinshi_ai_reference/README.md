# Xinshi AI task references

Optional guards for reference-only conversation storage. This module does not
query analytics, verify Node task existence or grant access to report bodies.

The protocol, responsibilities, deployment prerequisites and validation are maintained in
[Cross-business Plan](../../../../../xinshi-docs/stories/pro/ai/architecture/cross-business-plan.mdx),
section 7.6. No fields, content types, permissions or site updates are installed.

Its integration entry runs only through the sibling analytics disposable test runner
with `ANALYTICS_REFERENCE_TESTS=1`. Never run it in an existing Drupal site.
