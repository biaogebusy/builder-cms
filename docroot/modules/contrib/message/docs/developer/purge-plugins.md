# Custom purge plugins

Purge plugins decide which messages to delete for a template during cron. Built-
in plugins are `quota` and `days`.

## Plugin annotation

Place classes under `src/Plugin/MessagePurge` in your module (or follow the
discovery path used by `MessagePurgePluginManager`):

```php
/**
 * @MessagePurge(
 *   id = "my_purge",
 *   label = @Translation("My purge", context = "MessagePurge"),
 *   description = @Translation("Deletes messages matching custom rules."),
 * )
 */
class MyPurge extends MessagePurgeBase {
  // ...
}
```

## Interface contract

Implement `Drupal\message\MessagePurgeInterface` (or extend
`MessagePurgeBase`):

| Method | Responsibility |
|--------|----------------|
| `fetch(MessageTemplateInterface $template)` | Return message IDs to purge |
| `process(array $ids)` | Act on those IDs (base class queues deletes) |
| Form methods | Collect plugin configuration |

`MessagePurgeBase::process()` chunks IDs into sets of
`MessagePurgeInterface::MESSAGE_DELETE_SIZE` (100) and enqueues them on the
`message_delete` queue.

## Extending the base class

Use `baseQuery($template)` from `MessagePurgeBase` for an access-unchecked query
scoped to the template, sorted by `created` and `mid` descending. Add your own
conditions in `fetch()`.

Inject the message entity query and `message_delete` queue in `create()`,
following `Quota` or `Days` as examples.

## Orchestration

You do not call plugins directly from cron. `message_cron()` invokes
`message.purge_orchestrator`, which:

1. Splits templates into global vs override
2. Instantiates configured plugins
3. Calls `fetch()` then `process()` for each

Global purge must be enabled (`purge_enable`) for non-overriding templates.
Overriding templates use their own `purge_methods` settings.

## Altering plugin definitions

Use `hook_message_purge_alter()` if you need to change discovered purge plugin
definitions.
