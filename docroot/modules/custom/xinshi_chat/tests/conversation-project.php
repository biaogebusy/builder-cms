<?php

/**
 * @file
 * Association checks invoked only by the isolated Chat HTTP fixture.
 */

use Drupal\node\Entity\Node;

function checkConversationProject(array $users, array $tokens, string $jar): void {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $chat = Node::create(['type' => 'conversation', 'uid' => $users['owner']->id(),
    'title' => 'Project chat', 'chat_type' => 'common', 'status' => 1]);
  $chat->save();
  // A collaborator may edit a project owned by someone else.
  $project = Node::create(['type' => 'project', 'uid' => $users['other']->id(),
    'title' => 'Collaborative project', 'status' => 1]);
  \Drupal::cache('entity')->set('chat-project-cache-sentinel', 'Unrelated cache item');
  $project->save();
  check((bool) \Drupal::cache('entity')->get('chat-project-cache-sentinel'),
    'Saving an empty project does not clear unrelated entity cache entries');
  $path = '/api/v3/ai/conversations/' . $chat->uuid() . '/projects/' . $project->uuid();
  $load = static function () use ($project, $storage): Node {
    $storage->resetCache([$project->id()]);
    return $storage->load($project->id());
  };
  foreach (['other' => 403, 'limited' => 403, 'blocked' => 401] as $name => $status) {
    check(http($path, $tokens[$name], 'PUT')['status'] === $status, 'Association rejects ' . $name);
  }
  check(http($path, NULL, 'PUT')['status'] === 403, 'Association rejects anonymous access');
  check(http($path, NULL, 'PUT', NULL, $jar)['status'] === 403, 'Association rejects cookie-only access');
  foreach (['view', 'edit'] as $operation) {
    \Drupal::state()->set('xinshi_chat_test.project_field_denial', $operation);
    check(http($path, $tokens['owner'], 'PUT')['status'] === 403 &&
      $load()->get('field_conversations')->isEmpty(), 'Association requires field ' . $operation . ' access');
  }
  \Drupal::state()->delete('xinshi_chat_test.project_field_denial');
  \Drupal::state()->set('xinshi_chat_test.denied_project', $project->id());
  check(http($path, $tokens['owner'], 'PUT')['status'] === 403 &&
    $load()->get('field_conversations')->isEmpty(), 'Project update denial preserves all relationships');
  \Drupal::state()->delete('xinshi_chat_test.denied_project');
  check(http($path, $tokens['owner'], 'PUT')['status'] === 204,
    'An authorized collaborator can link an owned conversation to another author project');
  $linked = $load();
  check((string) $linked->getRevisionUserId() === (string) $users['owner']->id(),
    'The project revision records the collaborator who confirmed the association');
  check(array_column($linked->get('field_conversations')->getValue(), 'target_id') === [$chat->id()],
    'Confirmed association is persisted');
  check(http($path, $tokens['owner'], 'PUT')['status'] === 204 &&
    $load()->getRevisionId() === $linked->getRevisionId(), 'Repeated association neither duplicates nor creates a revision');
  $stale = clone $linked;
  $next = Node::create(['type' => 'conversation', 'uid' => $users['owner']->id(),
    'title' => 'Next project chat', 'status' => 1]);
  $next->save();
  $next_path = '/api/v3/ai/conversations/' . $next->uuid() . '/projects/' . $project->uuid();
  check(http($next_path, $tokens['owner'], 'PUT')['status'] === 204, 'A second append preserves the first conversation');
  try {
    $stale->setTitle('Stale project');
    $stale->save();
    throw new RuntimeException('Stale project unexpectedly saved.');
  }
  catch (\Drupal\Core\Entity\EntityStorageException $error) {
    check($error->getPrevious() instanceof \Drupal\xinshi_ai_reference\ConversationReferenceConflict,
      'Ordinary stale project saves cannot overwrite a confirmed association');
  }
  $ids = array_column($load()->get('field_conversations')->getValue(), 'target_id');
  check($ids === [$chat->id(), $next->id()], 'Both project conversations survive the rejected stale save');
  $last = Node::create(['type' => 'conversation', 'uid' => $users['owner']->id(),
    'title' => 'JSON API project chat', 'status' => 1]);
  $last->save();
  check(in_array(http('/jsonapi/node/project/' . $project->uuid() . '/relationships/field_conversations',
    $tokens['owner'], 'POST', ['data' => [['type' => 'node--conversation', 'id' => $last->uuid()]]])['status'], [200, 204], TRUE) &&
    count($load()->get('field_conversations')) === 3, 'Ordinary JSON:API appends share the revision guard');
  \Drupal::service('xinshi_chat.conversation_deletion')->delete($chat->uuid());
  $last_path = '/api/v3/ai/conversations/' . $last->uuid() . '/projects/' . $project->uuid();
  check(http($last_path, $tokens['owner'], 'PUT')['status'] === 204,
    'An already confirmed association permits an unrelated dangling reference');
  $fresh = Node::create(['type' => 'conversation', 'uid' => $users['owner']->id(),
    'title' => 'Fresh project chat', 'status' => 1]);
  $fresh->save();
  $fresh_path = '/api/v3/ai/conversations/' . $fresh->uuid() . '/projects/' . $project->uuid();
  $before = $load()->toArray();
  \Drupal::state()->set('xinshi_chat_test.fail_project', $project->id());
  try {
    \Drupal::service('xinshi_chat.conversation_project')->link($fresh->uuid(), $project->uuid());
    throw new RuntimeException('Failed project write unexpectedly committed.');
  }
  catch (\Drupal\Core\Entity\EntityStorageException $error) {
    check(str_contains($error->getMessage(), 'Synthetic project failure') && $load()->toArray() === $before,
      'A project storage failure rolls back its relationship and revision together');
  }
  \Drupal::state()->delete('xinshi_chat_test.fail_project');
  check(http($fresh_path, $tokens['owner'], 'PUT')['status'] === 204 &&
    count($load()->get('field_conversations')) === 3, 'A new append removes only already missing conversation links');
  check(http($path, $tokens['owner'], 'PUT')['status'] === 404, 'A deleted conversation cannot be newly associated');
  check(http($fresh_path, $tokens['owner'], 'PUT', ['attributes' => ['title' => 'Unexpected']])['status'] === 400,
    'Association does not silently accept unsupported payloads');
  check(http('/api/v3/ai/conversations/invalid/projects/' . $project->uuid(), $tokens['owner'], 'PUT')['status'] === 400,
    'Association rejects malformed UUIDs');
  check(http('/api/v3/ai/conversations/' . $fresh->uuid() . '/projects/' . $fresh->uuid(), $tokens['owner'], 'PUT')['status'] === 404,
    'A conversation cannot masquerade as a project');
  \Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('enabled', FALSE)->save();
  check(http($fresh_path, $tokens['owner'], 'PUT')['status'] === 503,
    'Disabled Chat also disables project association');
  \Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('enabled', TRUE)->save();
  $empty = Node::create(['type' => 'conversation', 'uid' => $users['owner']->id(), 'title' => 'Empty chat']);
  $empty->save();
  \Drupal::cache('entity')->set('chat-delete-cache-sentinel', 'Unrelated cache item');
  \Drupal::service('xinshi_chat.conversation_deletion')->delete($empty->uuid());
  check((bool) \Drupal::cache('entity')->get('chat-delete-cache-sentinel'),
    'Deleting an empty conversation does not clear unrelated entity cache entries');
}
