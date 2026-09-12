# Rendering and theming

## Getting message text

```php
// All partials for the message's rendering language.
$partials = $message->getText();

// Single partial (delta 0).
$first = $message->getText(NULL, 0);

// Explicit language.
$partials = $message->getText('es');
```

`getText()` returns an array of markup strings (one per partial, or a single
entry when `$delta` is set). It does not build a full entity render array.

You can also set the rendering language on the entity:

```php
$message->setLanguage('fr');
$text = $message->getText();
```

## Entity view builder

```php
$build = \Drupal::entityTypeManager()
  ->getViewBuilder('message')
  ->view($message, 'full');
```

`MessageViewBuilder` includes `partial_*` components according to the entity
view display for the template and view mode.

## Twig template

Default template: `templates/message.html.twig`.

Preprocess: `template_preprocess_message()` in `message.module` provides
variables such as `message`, `view_mode`, `content`, `teaser`, `page`, and
optionally `date` / `author_name`.

## Theme suggestions

`message_theme_suggestions_message()` adds:

- `message__{view_mode}`
- `message__{bundle}`
- `message__{bundle}__{view_mode}`
- `message__{id}`
- `message__{id}__{view_mode}`

Example: for template `example_create_node` in view mode `full`, suggestions
include `message--example-create-node--full.html.twig`.

## Hooks

- `hook_message_view()` — alter the message before rendering
- `hook_message_view_alter()` — alter the built render array

See [Hooks and services](hooks-services.md).
