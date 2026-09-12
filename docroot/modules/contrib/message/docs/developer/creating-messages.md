# Creating messages

Create message entities in response to events (entity insert hooks, custom
services, queue workers, and so on).

## Basic example

```php
use Drupal\message\Entity\Message;

$message = Message::create([
  'template' => 'example_create_node',
  'uid' => $account->id(),
]);
$message->set('field_node_reference', $node);
$message->save();
```

`save()` requires a valid template. If the template is missing, a
`MessageException` is thrown.

## Setting arguments

```php
$message->setArguments([
  '@noun' => 'article',
  '@detail' => [
    'callback' => 'my_module_format_detail',
    'arguments' => [$node->id()],
  ],
]);
$message->save();
```

Placeholders in the template text (for example `@noun`) are replaced when the
message is rendered. See [Arguments and callbacks](arguments-callbacks.md).

## Single-use tokens at save time

If the template text contains single-use tokens such as `@{message:user-name}`,
`save()` resolves them via the Token service and merges the results into the
stored arguments. Those values are then fixed for later displays.

## From the example module

`message_example` creates messages on node, comment, and user insert:

```php
function message_example_node_insert(Node $node) {
  $message = Message::create([
    'template' => 'example_create_node',
    'uid' => $node->get('uid'),
  ]);
  $message->set('field_node_reference', $node);
  $message->set('field_published', $node->isPublished());
  $message->save();
}
```

## Querying messages by template

```php
$ids = Message::queryByTemplate('example_create_node');
$messages = Message::loadMultiple($ids);
```

Or use an entity query when you need extra conditions:

```php
$ids = \Drupal::entityQuery('message')
  ->accessCheck(FALSE)
  ->condition('template', 'example_create_node')
  ->condition('field_node_reference.target_id', $node->id())
  ->execute();
```

## Deleting messages

Call `$message->delete()` (or load multiple and delete each).  
`Message::deleteMultiple()` is **deprecated** as of Message 1.2.0 and will be
removed in 2.0.0.
