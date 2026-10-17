# CLAUDE.md

Builder CMS is a Drupal project. Read and follow the shared project guidance below
before working in this repository:

@AGENTS.md

Before changing unclear behavior, confirm ordinary frontend logic by reading
`../xinshi-base`, and AI or Node/Harness logic by reading `../xinshi-pro`.
Make shared frontend changes only in `../xinshi-base`; a later branch merge brings
them to Pro. Do not copy shared changes directly into `../xinshi-pro`.
Follow the repository-boundary and caller-verification rules in `AGENTS.md`.

Permission behavior and existing-site compatibility are maintained in
[Permission rules](../xinshi-docs/stories/develop/engineering/permissions.mdx).
Follow the shared permission-documentation requirements in `AGENTS.md`.

When creating or updating documentation, follow the mandatory
[ASD-STE100 writing rules](AGENTS.md#documentation-writing-asd-ste100) and the shared
[terminology and writing guide](../xinshi-docs/stories/develop/engineering/terminology.mdx).
The imported rules distinguish English ASD-STE100 requirements from Chinese clear-expression
principles and require factual preservation and verification.

`AGENTS.md` is the single source for repository boundaries, Drupal conventions,
validation, and the requirement to update the corresponding xinshi-docs pages and
CMS remediation progress with each change. Edit shared rules there rather than
duplicating them in this file.
