<?php

declare(strict_types=1);

/**
 * @file
 * Aggregate deletion checks invoked only by the isolated Chat HTTP fixture.
 */

use Drupal\node\Entity\Node;

function checkConversationDeletion(array $users, array $tokens, string $jar): void {
  \Drupal\user\Entity\Role::load('chat_owner')->grantPermission('delete own conversation content')
    ->grantPermission('delete own ai_session content')->save();
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $message = static function (string $owner) {
    $node = Node::create(['type' => 'ai_session', 'uid' => $owner, 'title' => 'Deletion fixture',
      'session_role' => 'user', 'storage_version' => 3, 'edit_version' => 1, 'storage_mode' => 'inline',
      'content' => ['value' => 'Ordinary message', 'format' => 'plain_text']]);
    $node->save();
    return $node;
  };
  $conversation = static function (string $owner, array $messages) {
    $node = Node::create(['type' => 'conversation', 'uid' => $owner, 'title' => 'Deletion fixture',
      'model' => 'fixture', 'platform' => 'fixture', 'chat_type' => 'common',
      'sessions' => array_map(static fn($message) => ['target_id' => $message->id()], $messages)]);
    $node->save();
    return $node;
  };
  $exists = static function ($node) use ($storage): bool {
    $storage->resetCache([$node->id()]);
    return $storage->load($node->id()) !== NULL;
  };
  $owner = (string) $users['owner']->id();
  $first = $message($owner);
  $shared = $message($owner);
  $historical = $message($owner);
  $gone = $message($owner);
  $chat = $conversation($owner, [$first, $first, $shared, $historical, $gone]);
  $other = $conversation($owner, []);
  check(http('/jsonapi/node/conversation/' . $other->uuid() . '/relationships/sessions',
    $tokens['owner'], 'POST', ['data' => [['type' => 'node--ai_session', 'id' => $shared->uuid()]]])['status'] === 204,
    'Ordinary JSON:API relationship writes participate in the reference guard');
  $revision = $conversation($owner, [$historical]);
  $revision->setNewRevision(TRUE);
  $revision->set('sessions', []);
  $revision->save();
  $gone->delete();
  $chat->setTitle('Retained dangling link');
  $chat->save();
  check(TRUE, 'An existing dangling link does not block an ordinary conversation save');
  $path = '/api/v3/ai/conversations/' . $chat->uuid();
  check(http($path, $tokens['other'], 'DELETE')['status'] === 403,
    'Aggregate deletion rejects another author');
  check(http($path, $tokens['limited'], 'DELETE')['status'] === 403,
    'Aggregate deletion retains effective OAuth scope restrictions');
  check(http($path, NULL, 'DELETE', NULL, $jar)['status'] === 403,
    'Aggregate deletion is OAuth-only even with a valid cookie');
  check(http($path, $tokens['blocked'], 'DELETE')['status'] === 401,
    'Aggregate deletion rejects a blocked account');
  check($exists($chat) && $exists($first), 'Denied deletion preserves the entire conversation');
  \Drupal::state()->set('xinshi_chat_test.hidden_sessions', $chat->id());
  \Drupal::service('cache_tags.invalidator')->invalidateTags($chat->getCacheTags());
  check(http($path, $tokens['owner'], 'DELETE')['status'] === 403,
    'Unreadable relationships block aggregate deletion');
  \Drupal::state()->delete('xinshi_chat_test.hidden_sessions');
  \Drupal::state()->set('xinshi_chat_test.denied_delete', $first->id());
  check(http($path, $tokens['owner'], 'DELETE')['status'] === 403 && $exists($chat) && $exists($first),
    'Message deletion denial leaves the parent and all children unchanged');
  \Drupal::state()->delete('xinshi_chat_test.denied_delete');
  check(http($path, $tokens['owner'], 'DELETE')['status'] === 204,
    'One request deletes the conversation, duplicate links and unshared messages');
  check(!$exists($chat) && !$exists($first) && !$exists($gone),
    'Deletion removes all revisions of the parent and its unshared current messages');
  check($exists($shared) && $exists($historical) && $exists($other) && $exists($revision),
    'Current and historical references in other conversations preserve shared messages');
  check(http($path, $tokens['owner'], 'DELETE')['status'] === 204,
    'Retry after an ignored success acknowledgement remains successful');
  check(http('/api/v3/ai/conversations/not-a-uuid', $tokens['owner'], 'DELETE')['status'] === 400,
    'Deletion rejects an invalid conversation identity');

  $owned = $message($owner);
  $foreign = $message((string) $users['other']->id());
  $mixed = $conversation($owner, [$owned, $foreign]);
  check(http('/api/v3/ai/conversations/' . $mixed->uuid(), $tokens['owner'], 'DELETE')['status'] === 403 &&
    $exists($mixed) && $exists($owned) && $exists($foreign),
    'A foreign unshared message cannot be deleted under the parent author authority');

  $rollback_message = $message($owner);
  $rollback = $conversation($owner, [$rollback_message]);
  \Drupal::state()->set('xinshi_chat_test.fail_delete', $rollback->id());
  try {
    \Drupal::service('xinshi_chat.conversation_deletion')->delete($rollback->uuid());
    throw new RuntimeException('Expected a synthetic storage failure.');
  }
  catch (\Drupal\Core\Entity\EntityStorageException $error) {
    check(str_contains($error->getMessage(), 'Synthetic deletion failure'),
      'Storage failure reaches the aggregate transaction');
  }
  \Drupal::state()->delete('xinshi_chat_test.fail_delete');
  check($exists($rollback) && $exists($rollback_message),
    'A failed delete hook rolls back parent and message storage');

  $stale = clone $chat;
  try {
    $stale->save();
    throw new RuntimeException('Expected stale save rejection.');
  }
  catch (\Drupal\Core\Entity\EntityStorageException $error) {
    check($error->getPrevious() instanceof \Drupal\xinshi_ai_reference\ConversationReferenceConflict,
      'A stale entity cannot resurrect a deleted conversation');
  }
  try {
    $conversation($owner, [$first]);
    throw new RuntimeException('Expected missing target rejection.');
  }
  catch (\Drupal\Core\Entity\EntityStorageException $error) {
    check($error->getPrevious() instanceof \Drupal\xinshi_ai_reference\ConversationReferenceConflict,
      'A new conversation cannot link a message deleted by the aggregate operation');
  }
  foreach (['xinshi_chat.conversation.delete', 'xinshi_ai.conversation.update',
    'jsonapi.node--conversation.relationship.post', 'jsonapi.node--article.relationship.post'] as $route) {
    $request = \Symfony\Component\HttpFoundation\Request::create('/fixture');
    $request->attributes->set('_route', $route);
    $error = new \Drupal\Core\Entity\EntityStorageException('Private SQL diagnostic', 0,
      new \PDOException('Private SQL diagnostic', 40001));
    $event = new \Symfony\Component\HttpKernel\Event\ExceptionEvent(\Drupal::service('http_kernel'), $request,
      \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST, $error);
    \Drupal::service('xinshi_ai_reference.responses')->onException($event);
    if (str_contains($route, 'article')) {
      check(!$event->hasResponse(), 'Conversation conflict handling leaves unrelated writers unchanged');
    }
    else {
      check($event->getResponse()?->getStatusCode() === 409 &&
        $event->getResponse()->getContent() === '{"error":"conversation_reference_conflict"}',
        'Wrapped database serialization failures expose only a retryable conversation conflict');
    }
  }
}
