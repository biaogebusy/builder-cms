# Hooks and services

## Services

| Service | Usage |
|---------|--------|
| `plugin.manager.message.purge` | Create purge plugin instances / get definitions |
| `message.purge_orchestrator` | `purgeAllTemplateMessages()` from cron or custom code |

```php
\Drupal::service('message.purge_orchestrator')->purgeAllTemplateMessages();
```

## Hooks

Documented in `message.api.php`:

### `hook_message_view()`

Act on a message while it is assembled for rendering. Add elements to
`$message->content`.

### `hook_message_view_alter()`

Alter the full render array after it is built. Useful for weight changes or
`#post_render` callbacks.

### `hook_form_message_template_form_alter()`

Alter the message template entity form. Bundle-specific variants follow entity
form alter naming conventions.

## Legacy / unused hooks in the API file

`message.api.php` still documents D7-era hooks such as
`hook_default_message_template()` and `hook_default_message_category()`. Prefer
configuration entities and optional config YAML for default templates. Do not
rely on message categories; that storage is not part of the current module.

## Entity and module hooks used internally

Message implements several Drupal hooks in `message.module`, including:

- `hook_entity_delete()` — queue cascade deletes for configured entity types
- `hook_entity_extra_field_info()` — expose `partial_*` display components
- `hook_cron()` — run the purge orchestrator
- Token hooks for message-related tokens

## Theme hooks

- `hook_theme()` — registers the `message` theme
- `hook_theme_suggestions_message()` — per bundle / view mode / id suggestions

See [Rendering and theming](rendering-theming.md).
