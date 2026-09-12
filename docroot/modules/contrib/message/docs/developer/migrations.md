# Migrations from Drupal 7

Message provides Migrate API plugins and YAML migrations to bring Drupal 7
message types and messages into current Message templates and entities.

## Provided migrations

| Migration ID | Source | Destination |
|--------------|--------|-------------|
| `d7_message_template` | D7 message types | `entity:message_template` |
| `d7_message` | D7 messages | `entity:message` |

Run **templates first**. `d7_message` lists `d7_message_template` (and
`d7_user`) as required dependencies. Templates also require
`d7_filter_format`.

```bash
drush migrate:import d7_message_template
drush migrate:import d7_message
```

## Plugins

Under `src/Plugin/migrate/`:

| Plugin | Role |
|--------|------|
| Source `d7_message_template_source` | Read D7 message types |
| Source `d7_message_source` | Read D7 messages (optional bundle filter) |
| Process `d7_message_template_text` | Assemble multi-delta text / formats |
| Process `d7_message_arguments` | Map D7 arguments (including `!` → `@` style rewrites) |
| Destination `MessageTemplateDestination` | Save template entities |

## Mapping notes

- D7 message **type** machine names become template IDs (subject to length
  limits in the migration process pipeline).
- Message `type` maps to the `template` field on the destination entity.
- Arguments are processed so stored placeholders remain usable at render time.
- Language falls back to `und` when missing.

## After migration

1. Review templates under `/admin/structure/message`
2. Confirm filter formats used by partials still exist
3. Rebuild caches and verify a sample of messages render as expected
4. Revisit purge and `delete_on_entity_delete` settings for the new site
