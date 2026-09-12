# Installing and configuring

## Installation

Install and enable the Message module like any other contributed module, for
example with Composer and Drush:

```bash
composer require drupal/message
drush en message -y
```

Optional but recommended:

- **Token** — richer tokens and a token browser on template forms
- **Field UI** — manage fields and displays for message templates
- **Views** — list and filter messages (an admin view is provided when Views is
  available)
- **message_example** — sample templates and hooks that create messages (see
  the [example module](../developer/message-example.md) docs)

## Global settings

Open **Configuration → Message → Message settings**
(`/admin/config/message/message`).

### Purge messages

Enable **Purge messages** to delete messages on cron according to the selected
purge methods (quota and/or age in days). Templates can override these settings;
see [Purging and cleanup](purge.md).

### Delete messages when referenced entities are deleted

When a referenced entity is deleted, Message can queue related messages for
deletion. By default this applies to:

- Comment
- Node
- Taxonomy term
- User

Adjust the entity types under the same settings form. Behavior depends on field
cardinality:

- Single-value references: the message is queued for deletion
- Multi-value references: the message is deleted only when the last referenced
  entity of that type is gone

## Permissions

Assign [permissions](permissions.md) so the right roles can manage templates,
administer messages, and view the messages overview.
