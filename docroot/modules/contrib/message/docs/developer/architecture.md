# Architecture

Message is built around two entity types and a small set of services.

## Entity types

### Message template (`message_template`)

A **configuration entity** that acts as the bundle for messages. It stores:

- Label, description, and machine name (`template` ID)
- Multi-delta `text` (text_format partials)
- Settings such as token options and purge overrides

Class: `Drupal\message\Entity\MessageTemplate`

Exported as `message.template.{id}` configuration.

### Message (`message`)

A **content entity** instance of a template. Base fields include:

| Field | Purpose |
|-------|---------|
| `mid` | Message ID |
| `uuid` | UUID |
| `template` | Reference to the message template |
| `langcode` | Language code |
| `uid` | Author |
| `created` / `changed` | Timestamps |
| `arguments` | Map of placeholders for rendering |

Class: `Drupal\message\Entity\Message`  
Interface: `Drupal\message\MessageInterface`

Additional fields can be attached per template via the Field API.

## Rendering pipeline

When `Message::getText()` runs:

1. Load the template (override-free config for tokens/translations as
   implemented by the template entity)
2. Load filter-processed template text for the requested language and delta
3. Replace stored **arguments** (including callback results)
4. If token replacement is enabled on the template, run the Token service with
   `['message' => $this]`

Entity view builds use `MessageViewBuilder`, which maps selected `partial_*`
components from the entity view display into the render array.

## Services

Defined in `message.services.yml`:

| Service ID | Class | Role |
|------------|-------|------|
| `plugin.manager.message.purge` | `MessagePurgePluginManager` | Discover and instantiate purge plugins |
| `message.purge_orchestrator` | `MessagePurgeOrchestrator` | Apply global/template purge config on cron |

## Plugin type: Message purge

Plugins annotated with `@MessagePurge` live under
`Drupal\message\Plugin\MessagePurge`. Built-ins: `quota`, `days`. See
[Custom purge plugins](purge-plugins.md).

## Queues

- `message_delete` — delete chunks of message IDs (purge and some cascade
  deletes)
- `message_check_delete` — for multi-value references, delete a message only
  when the last referenced entity is gone

## Module layout (high level)

```text
src/Entity/          Message, MessageTemplate
src/Form/            Settings and template forms
src/Plugin/          Purge, Views, Migrate plugins
message.api.php      Hooks
migrations/          D7 migrations
modules/message_example/
```
