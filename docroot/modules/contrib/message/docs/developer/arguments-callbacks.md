# Arguments and callbacks

Message arguments are stored on the entity in the `arguments` map field. They
supply values for placeholders in template text at display time (after
single-use tokens have already been baked in at save).

## Static arguments

```php
$message->setArguments([
  '@name' => 'Jane Doe',
  '%title' => $node->label(),
]);
```

Keys should match placeholders in the template. Replacement uses Drupal's
`FormattableMarkup`, so the usual `@`, `%`, and `!` prefix rules apply.

## Callback arguments

An argument value may be an array with a callable:

```php
$message->setArguments([
  '@summary' => [
    'callback' => 'my_module_message_summary',
    'arguments' => [$node->id(), 'teaser'],
  ],
]);
```

At render time Message calls the callback with the given arguments and uses the
return value as the replacement.

### Passing the message entity

Set `pass message` to `TRUE` to add the `Message` object into the callback
`arguments` array (under the `message` key) before `call_user_func_array`
runs. With an empty `arguments` list, the message is the first callback
parameter:

```php
$message->setArguments([
  '@label' => [
    'callback' => 'my_module_label_from_message',
    'arguments' => [],
    'pass message' => TRUE,
  ],
]);
```

```php
function my_module_label_from_message(\Drupal\message\Entity\Message $message): string {
  return $message->getOwner()->getDisplayName();
}
```

!!! note
    The callback must be callable at display time (`is_callable`). Prefer
    well-known functions or static methods that remain available when the
    message is viewed.

## Interaction with tokens

Order in `getText()`:

1. Template text is loaded
2. Arguments (including callbacks) are applied
3. Dynamic tokens are replaced if the template has token replace enabled

Single-use `@{token}` values are converted to stored arguments during `save()`,
before later displays run this pipeline.
