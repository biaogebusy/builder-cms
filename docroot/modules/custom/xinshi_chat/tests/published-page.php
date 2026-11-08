<?php

/**
 * @file
 * Explicit publication keeps private drafts and provenance outside the anonymous projection.
 */

use Drupal\node\Entity\Node;

function checkPublishedPage(array $users, array $tokens, string $jar): void {
  $html = '<main>Published HTML & "quoted" 中文</main>';
  $content = static fn(string $html): string => '```json' . "\n" . json_encode([
    'widget' => ['type' => 'fullHTML', 'html' => $html],
    'uiRevision' => ['original' => 'PRIVATE ORIGINAL', 'sourceId' => 'PRIVATE SOURCE ID'],
  ], JSON_UNESCAPED_UNICODE) . "\n```";
  $message = Node::create(['type' => 'ai_session', 'title' => 'PRIVATE TITLE',
    'uid' => $users['owner']->id(), 'status' => 1, 'session_role' => 'assistant',
    'storage_version' => 3, 'edit_version' => 1, 'storage_mode' => 'inline',
    'content' => ['value' => $content($html), 'format' => 'plain_text']]);
  $message->save();
  $chat = Node::create(['type' => 'conversation', 'title' => 'PRIVATE CHAT',
    'model' => 'fixture', 'platform' => 'fixture',
    'uid' => $users['owner']->id(), 'status' => 1, 'sessions' => [['target_id' => $message->id()]]]);
  $chat->save();
  $base = '/api/v3/ai/conversations/' . $chat->uuid() . '/sessions/' . $message->uuid();
  $manage = $base . '/published-page';
  $payload = ['html_hash' => hash('sha256', $html)];
  $public_path = static fn(string $uuid): string => '/api/v3/ai/published-pages/' . $uuid;
  check(http($manage, $tokens['owner'])['data'] === ['page' => NULL], 'Reading share settings does not publish');
  check(http($public_path($message->uuid()))['status'] === 404, 'Old message UUID is never a public page');
  foreach (['other' => 403, 'admin' => 403, 'limited' => 403, 'blocked' => 401, 'anonymous' => 403] as $reader => $status) {
    foreach (['GET', 'PUT', 'DELETE'] as $method) {
      check(http($manage, $tokens[$reader] ?? NULL, $method, $method === 'PUT' ? $payload : NULL)['status'] === $status,
        $reader . ' cannot ' . $method . ' another author publication');
    }
  }
  foreach (['GET', 'PUT', 'DELETE'] as $method) {
    check(http($manage, NULL, $method, $method === 'PUT' ? $payload : NULL, $jar)['status'] === 403,
      'Publication management is OAuth-only: ' . $method);
  }
  foreach ([[], ['html_hash' => []], ['html_hash' => 'bad'], $payload + ['html' => 'injected']] as $invalid) {
    check(http($manage, $tokens['owner'], 'PUT', $invalid)['status'] === 422, 'Invalid publication input is rejected');
  }
  check(http($manage, $tokens['owner'], 'PUT', ['html_hash' => str_repeat('0', 64)])['status'] === 409,
    'Stale visible HTML cannot be published');
  check(http($manage, $tokens['owner'])['data'] === ['page' => NULL], 'Failed publishing has no public side effect');
  $protected_id = \Drupal::service('uuid')->generate();
  $protected = Node::create(['type' => 'ai_session', 'uuid' => $protected_id,
    'uid' => $users['owner']->id(), 'status' => 1, 'title' => 'Protected task result',
    'session_role' => 'assistant', 'storage_version' => 3, 'edit_version' => 1,
    'storage_mode' => 'protected_reference', 'run_id' => $protected_id,
    'result_reference' => json_encode(['kind' => 'xinshi-protected-run', 'version' => 1,
      'runId' => $protected_id, 'chatId' => $chat->uuid()])]);
  $protected->save();
  \Drupal::service('xinshi_chat.conversation_writer')->update($chat->uuid(), [$protected_id], []);
  $protected_path = '/api/v3/ai/conversations/' . $chat->uuid() . '/sessions/' . $protected_id . '/published-page';
  check(http($protected_path, $tokens['owner'], 'PUT', $payload)['status'] === 422,
    'Protected references cannot be published as HTML');
  check(http('/jsonapi/node/ai_session/' . $protected_id, $tokens['other'])['status'] === 403,
    'Ordinary privacy hooks preserve protected-reference isolation');
  $extras = [$protected];
  foreach ([['user', $content($html)], ['assistant', '{"widget":{"type":"custom","html":"invalid"}}'],
    ['assistant', 'not json']] as [$role, $body]) {
    $invalid = Node::create(['type' => 'ai_session', 'title' => 'Nonpublishable source',
      'uid' => $users['owner']->id(), 'status' => 1, 'session_role' => $role,
      'storage_version' => 3, 'edit_version' => 1, 'storage_mode' => 'inline',
      'content' => ['value' => $body, 'format' => 'plain_text']]);
    $invalid->save();
    \Drupal::service('xinshi_chat.conversation_writer')->update($chat->uuid(), [$invalid->uuid()], []);
    check(http('/api/v3/ai/conversations/' . $chat->uuid() . '/sessions/' . $invalid->uuid() . '/published-page',
      $tokens['owner'], 'PUT', $payload)['status'] === 422, 'Only valid saved assistant FullHTML can be published');
    $extras[] = $invalid;
  }
  $published = http($manage, $tokens['owner'], 'PUT', $payload);
  $id = $published['data']['page']['id'] ?? '';
  check($published['status'] === 200 && $id !== $message->uuid() && strlen($id) === 36,
    'Explicit publishing allocates an independent public UUID');
  check(http($public_path($id))['data'] === ['html' => $html], 'Anonymous DTO contains only the published HTML');
  check(http('/jsonapi/node/ai_session/' . $message->uuid())['status'] === 403,
    'Publishing never opens private message JSON:API');
  check(http($manage, $tokens['owner'], 'PUT', $payload)['data']['page']['id'] === $id,
    'Retrying explicit publication keeps the current public identifier');
  \Drupal::state()->set('xinshi_chat_test.hidden_sessions', $chat->id());
  check(http($manage, $tokens['owner'], 'PUT', $payload)['status'] === 403, 'Publication respects source field denial');
  \Drupal::state()->delete('xinshi_chat_test.hidden_sessions');
  $new_html = '<main>Explicit update</main>';
  check(http($base . '/fields', $tokens['owner'], 'PATCH', ['expected_edit_version' => 1,
    'fields' => ['content' => ['value' => $content($new_html), 'format' => 'plain_text']]])['status'] === 200,
    'Private source can be edited after publication');
  check(http($public_path($id))['data'] === ['html' => $html], 'Editing source does not silently update public HTML');
  check(http($manage, $tokens['owner'], 'PUT', $payload)['status'] === 409, 'Old preview cannot overwrite a newer source');
  $payload['html_hash'] = hash('sha256', $new_html);
  check(http($manage, $tokens['owner'], 'PUT', $payload)['data']['page']['editVersion'] === 2,
    'Explicit update records the new source version');
  check(http($public_path($id))['data'] === ['html' => $new_html], 'Explicit update replaces only public HTML');
  \Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('enabled', FALSE)->save();
  check(http($public_path($id))['status'] === 503 && http($manage, $tokens['owner'])['status'] === 503,
    'Disabled Chat stops publication and public reads');
  \Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('enabled', TRUE)->save();
  check(http($manage, $tokens['owner'], 'DELETE')['status'] === 204, 'Author explicitly revokes publication');
  check(http($public_path($id))['status'] === 404, 'Revocation invalidates the public URL');
  check(http($base . '/fields', $tokens['owner'])['data']['session']['content']['value'] === $content($new_html),
    'Revocation leaves the private source intact');
  $new_id = http($manage, $tokens['owner'], 'PUT', $payload)['data']['page']['id'];
  check($id !== $new_id, 'Republishing creates a new URL');
  $blocked_author = $users['blocked'];
  $blocked_author->activate()->save();
  \Drupal::currentUser()->setAccount($blocked_author);
  $blocked_message = $message->createDuplicate();
  $blocked_message->setOwnerId($blocked_author->id());
  $blocked_message->save();
  $blocked_chat = $chat->createDuplicate();
  $blocked_chat->setOwnerId($blocked_author->id())->set('sessions', [['target_id' => $blocked_message->id()]]);
  $blocked_chat->save();
  $blocked_page = \Drupal::service('xinshi_chat.published_page')->publish($blocked_chat->uuid(),
    $blocked_message->uuid(), hash('sha256', $html))['page']['id'];
  check(http($public_path($blocked_page))['status'] === 200, 'An active author can publish an independent page');
  $blocked_author->block()->save();
  check(http($public_path($blocked_page))['status'] === 404, 'Blocked authors cannot continue public distribution');
  $blocked_chat->delete();
  $blocked_message->delete();
  \Drupal::currentUser()->setAccount($users['owner']);
  $chat->delete();
  check(http($public_path($new_id))['status'] === 404, 'Conversation deletion revokes its independent pages');
  $message->delete();
  foreach ($extras as $extra) $extra->delete();
}
