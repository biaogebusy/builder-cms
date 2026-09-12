# Message templates

Message templates define the structure and text used when logging events.

## Managing templates

Go to **Structure → Message templates** (`/admin/structure/message`).

| Action | Path |
|--------|------|
| List templates | `/admin/structure/message` |
| Add template | `/admin/structure/message/template/add` |
| Edit template | `/admin/structure/message/manage/{template}` |
| Delete template | `/admin/structure/message/delete/{template}` |

You need the **Administer message templates** permission.

## Template fields

When creating or editing a template you typically configure:

- **Label** — human-readable name
- **Machine name** — used as the message bundle ID
- **Description** — administrative notes
- **Message text** — one or more text partials (see
  [Partials and displays](partials-displays.md))
- **Token options** — whether tokens are replaced on display, and whether unused
  tokens are cleared
- **Purge** — optionally override [global purge settings](purge.md)

## Fields on messages

Each template is a bundle of the `message` entity type. With Field UI enabled
you can add fields under the template's manage fields screens, for example:

`/admin/structure/message/manage/{template}/fields`

Common patterns:

- Entity reference fields (node, comment, user) so tokens and Views can use
  related content
- Boolean or other fields used for filtering (for example a published flag)

## Configuration export

Templates are stored as configuration (`message.template.*`). Export them with
your site's normal configuration management workflow so they can be deployed
across environments.
