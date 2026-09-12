# Permissions

Ace Editor defines a single permission in `ace_editor.permissions.yml`:

| Permission | Machine name | Notes |
| --- | --- | --- |
| Administer AceEditor | `administer ace_editor` | Marked *restrict access* — grant only to trusted roles. |

Day-to-day use of the module is governed by core permissions rather than this
one:

- **Configuring a text editor / filter on a format** requires
  *Administer filters* (`administer filters`).
- **Configuring a field's display formatter** requires the relevant
  *Administer … display* permissions for that entity type/bundle.
- **Using** the editor while creating/editing content requires the normal
  create/edit permissions for the content, plus access to a text format that has
  the Ace editor assigned.

Grant permissions at **People → Permissions** (`/admin/people/permissions`).

> Because the *Administer AceEditor* permission is flagged as restricted, assign
> it only to administrative roles you fully trust.
