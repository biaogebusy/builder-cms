# Custom tokens

Dynamic tokens in message text are replaced through Drupal's Token API with the
message entity as context (`['message' => $message]`).

## Providing tokens

Implement `hook_token_info()` and `hook_tokens()`. The
`message_example` submodule shows a full pattern in
`message_example.tokens.inc`.

```php
/**
 * Implements hook_token_info().
 */
function my_module_token_info() {
  $type = [
    'name' => t('Message'),
    'description' => t('Tokens related to messages.'),
    'needs-data' => 'message',
  ];

  $message['custom-label'] = [
    'name' => t('Custom label'),
    'description' => t('A custom label derived from the message.'),
  ];

  return [
    'types' => ['message' => $type],
    'tokens' => ['message' => $message],
  ];
}

/**
 * Implements hook_tokens().
 */
function my_module_tokens($type, $tokens, array $data = [], array $options = []) {
  $replacements = [];

  if ($type === 'message' && !empty($data['message'])) {
    /** @var \Drupal\message\Entity\Message $message */
    $message = $data['message'];

    foreach ($tokens as $name => $original) {
      if ($name === 'custom-label') {
        $replacements[$original] = $message->label();
      }
    }
  }

  return $replacements;
}
```

Use those tokens in template text as `[message:custom-label]`.

## Field and entity tokens

With Token (and core entity token support), referenced fields on the message are
available, for example:

```text
[message:field_node_reference:entity:title]
[message:field_node_reference:entity:url]
```

Prefer standard entity tokens such as `[message:uid:entity:name]` over older
aliases when possible. Message still documents a deprecated `message:author`
style token path in older code paths; new code should use current Token naming.

## Single-use form

To freeze a token value at creation, wrap it in the single-use syntax in the
template text:

```text
@{message:custom-label}
```

That value is stored on the message at `save()` time.
