# CLAUDE.md

Builder CMS is a Drupal project. Read and follow the shared project guidance below
before working in this repository:

@AGENTS.md

Before changing unclear behavior, confirm ordinary frontend logic by reading
`../xinshi-base`, and AI or Node/Harness logic by reading `../xinshi-pro`.
Make shared frontend changes only in `../xinshi-base`; a later branch merge brings
them to Pro. Do not copy shared changes directly into `../xinshi-pro`.
Follow the repository-boundary and caller-verification rules in `AGENTS.md`.

`AGENTS.md` is the single source for repository boundaries, Drupal conventions,
validation, and the requirement to update the corresponding xinshi-docs pages and
CMS remediation progress with each change. Edit shared rules there rather than
duplicating them in this file.
