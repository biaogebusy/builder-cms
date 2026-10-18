<?php

declare(strict_types=1);

/**
 * @file
 * Complete ordered history, explicit protocol and access checks on the disposable site.
 */

use Drupal\node\Entity\Node;

if (getenv('XINSHI_ANALYTICS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  \Drupal::database()->getConnectionOptions()['database'] !== '/tmp/analytics-test/site.sqlite') {
  throw new RuntimeException('Use the disposable reference runner only.');
}
$history_checks = $GLOBALS['checks'];
\Drupal\xinshi_ai\Service\ChatSessionFields::install(3);
\Drupal::currentUser()->setAccount($users['owner']);
$history = \Drupal::service('xinshi_ai_reference.history');
$history_config = \Drupal::configFactory()->getEditable('xinshi_ai_reference.settings');
check(\Drupal::service('xinshi_ai_reference.storage')->version() === 1,
  'The legacy history fixture uses the explicitly selected legacy protocol');
$history_chat = Node::create(['type' => 'conversation', 'title' => 'Paged history',
  'uid' => $users['owner']->id(), 'status' => 1]);
$history_chat->save();
$history_ids = [];
$history_uuids = [];
for ($i = 0; $i < 103; $i++) {
  $item = Node::create(['type' => 'ai_session', 'title' => 'History ' . $i,
    'uid' => $users['owner']->id(), 'status' => 1, 'session_role' => 'user',
    'content' => ['value' => 'Body ' . $i, 'format' => 'plain_text'], 'created' => 1792000000]);
  $item->save();
  array_unshift($history_ids, (string) $item->id());
  array_unshift($history_uuids, $item->uuid());
}
$history_chat->set('sessions', array_map(fn($id) => ['target_id' => $id], $history_ids));
$history_chat->save();
// Ordinary administrative previews retain their existing Drupal grant; protected locators do not.
\Drupal\user\Entity\Role::create(['id' => 'history_admin', 'label' => 'History admin',
  'permissions' => ['access content', 'bypass node access']])->save();
\Drupal\simple_oauth\Entity\Oauth2Scope::create(['name' => 'history_admin', 'granularity_id' => 'role',
  'granularity_configuration' => ['role' => 'history_admin'],
  'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
$history_admin = \Drupal\user\Entity\User::create(['name' => 'history-admin',
  'status' => 1, 'roles' => ['history_admin']]);
$history_admin->save();
$admin_scope = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier('history_admin');
$token_repo = \Drupal::service('simple_oauth.repositories.access_token');
$admin_token = $token_repo->getNewToken($client, [$admin_scope], (string) $history_admin->id());
$admin_token->setIdentifier(bin2hex(random_bytes(24)));
$admin_token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
$admin_token->setPrivateKey(new \League\OAuth2\Server\CryptKey('/tmp/analytics-test/private.pem'));
$token_repo->persistNewAccessToken($admin_token);
$admin_bearer = $admin_token->convertToJWT()->toString();
$history_config->set('storage_version', 2)->save();
$inline_read_chat = Node::create(['type' => 'conversation', 'title' => 'Administrative ordinary preview',
  'uid' => $users['owner']->id(), 'status' => 1, 'sessions' => [['target_id' => $message->id()]]]);
$inline_read_chat->save();
$history_config->set('storage_version', 1)->save();
$server = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-S', '127.0.0.1:8080', __DIR__ . '/integration.php'],
  [0 => ['file', '/dev/null', 'r'], 1 => ['file', $fixture . '/reference-http.log', 'a'],
    2 => ['file', $fixture . '/reference-http.log', 'a']], $pipes);
try {
  for ($i = 0; $i < 60; $i++) {
    if ($socket = @fsockopen('127.0.0.1', 8080, $errno, $error, 0.1)) {
      fclose($socket);
      break;
    }
    usleep(100000);
  }
  $history_path = '/api/v3/ai/conversations/' . $history_chat->uuid() . '/sessions';
  $storage_path = '/api/v3/ai/chat-storage';
  $mode = http($storage_path, $bearers['owner']);
  check($mode['data'] === ['storage_version' => 1] && str_contains($mode['cache'], 'no-store'),
    'Authenticated protocol discovery returns only the explicit version without caching');
  foreach ([NULL, $bearers['other'], $scoped_bearer] as $token) {
    $response = http($history_path, $token);
    check($response['status'] === 403 && !isset($response['data']['messages']),
      'History requires the effective OAuth owner and entity view permission');
  }
  check(http($history_path, NULL, 'GET', NULL, $jar)['status'] === 403,
    'Cookie login cannot replace OAuth for history');
  check(http($history_path, $admin_bearer)['status'] === 200,
    'An effective Drupal bypass grant retains ordinary administrative history previews');
  $seen = [];
  $cursor = NULL;
  do {
    $page = http($history_path . ($cursor ? '?cursor=' . rawurlencode($cursor) : ''), $bearers['owner']);
    check($page['status'] === 200 && $page['data']['offset'] === count($seen) &&
      count($page['data']['messages']) <= 50 && $page['data']['total'] === 103,
      'Each history page is bounded with an exact offset and total');
    $seen = [...$seen, ...array_column($page['data']['messages'], 'id')];
    $cursor = $page['data']['next'];
  } while ($cursor !== NULL);
  check($seen === $history_uuids, 'All 103 same-time messages retain relation order without gaps or duplicates');
  $first = http($history_path, $bearers['owner'])['data'];
  $next = '?cursor=' . rawurlencode($first['next']);
  check(http($history_path . $next . 'broken', $bearers['owner'])['status'] === 400,
    'A modified cursor cannot choose an arbitrary offset');
  check(http('/api/v3/ai/conversations/' . $conversation->uuid() . '/sessions' . $next,
    $bearers['owner'])['status'] === 409, 'A cursor cannot be used for another conversation');
  foreach (['sessions', 'content', 'created'] as $denied_field) {
    \Drupal::state()->set('session_fields_test_denial', [$denied_field, 'view']);
    check(http($history_path, $bearers['owner'])['status'] === 403,
      'History cannot expose a denied ' . $denied_field . ' field');
    // Publish the cleared flag before the HTTP worker can flush an older state cache.
    \Drupal::state()->set('session_fields_test_denial', NULL);
  }
  $history_chat->set('sessions', array_map(fn($id) => ['target_id' => $id], array_reverse($history_ids)));
  $history_chat->save();
  check(http($history_path . $next, $bearers['owner'])['status'] === 409,
    'Relation reordering invalidates a cursor instead of skipping messages');
  $history_chat->set('sessions', [['target_id' => $history_ids[0]], ['target_id' => $history_ids[0]]]);
  $history_chat->save();
  check(http($history_path, $bearers['owner'])['status'] === 409, 'Duplicate relation entries fail explicitly');
  $history_chat->set('sessions', [['target_id' => 99999999]]);
  $history_chat->save();
  check(http($history_path, $bearers['owner'])['status'] === 409, 'Dangling relations are not silently omitted');
  $history_config->set('storage_version', 2)->save();
  check(http($storage_path, $bearers['owner'])['data'] === ['storage_version' => 2],
    'Only an explicit configuration change selects v2');
  $v2_path = '/api/v3/ai/conversations/' . $v2_chat->uuid() . '/sessions';
  $page = http($v2_path, $bearers['owner']);
  check($page['status'] === 200 && $page['data']['storage_version'] === 2 &&
    $page['data']['messages'][0]['fields']['storage_version'] === 2,
    'V2 history returns strict split-field snapshots');
  check(http($v2_path, $admin_bearer)['status'] === 403,
    'An administrator cannot read a conversation containing another author protected locator');
  $admin_path = '/api/v3/ai/conversations/' . $inline_read_chat->uuid() . '/sessions/' . $message->uuid() . '/fields';
  check(http($admin_path, $admin_bearer)['status'] === 200,
    'An administrator with current field access can preview ordinary v2 fields');
  check(http($admin_path, $admin_bearer, 'PATCH', ['expected_edit_version' => 5,
    'fields' => ['summary' => 'Unauthorized edit']])['status'] === 403,
    'Administrative preview does not grant another author CAS edits');
  $read_path = $v2_path . '/' . $message->uuid() . '/fields';
  $current = http($read_path, $bearers['owner']);
  check($current['status'] === 200 && $current['data']['session']['edit_version'] === 5,
    'An edit can obtain the current authorized snapshot independently of browser caches');
  \Drupal::state()->set('session_fields_test_denial', ['ui_review', 'view']);
  check(http($read_path, $bearers['owner'])['status'] === 403,
    'Current-field reads authorize metadata before disclosing a snapshot');
  \Drupal::state()->set('session_fields_test_denial', NULL);
  check(http('/api/v3/ai/conversations/' . $conversation->uuid() . '/sessions', $bearers['owner'])['status'] === 422,
    'V2 never decodes an unmigrated legacy record as a fallback');
  $legacy_post = ['data' => ['type' => 'node--ai_session', 'attributes' => [
    'title' => 'Old tab', 'session_role' => 'user', 'content' => ['value' => 'Old write', 'format' => 'plain_text']]]];
  check(http('/jsonapi/node/ai_session', $bearers['owner'], 'POST', $legacy_post)['status'] === 422,
    'After cutover an old browser cannot create legacy sessions');
  $legacy = Node::create(['type' => 'ai_session', 'title' => 'Old writer',
    'uid' => $users['owner']->id(), 'session_role' => 'user', 'content' => 'Old write']);
  rejected(fn() => $legacy->save(), 'After cutover direct legacy writers also fail');
  $old_link = clone $history_chat;
  $old_link->set('sessions', [['target_id' => $history_ids[0]]]);
  rejected(fn() => $old_link->save(), 'After cutover a stale browser cannot link an old-format orphan');

  $history_config->set('storage_version', 3)->save();
  check(http($storage_path, $bearers['owner'])['data'] === ['storage_version' => 3],
    'Explicit v3 activation is visible to fresh browser clients');
  $plan_chat = Node::create(['type' => 'conversation', 'title' => 'Independent Plan context',
    'uid' => $users['owner']->id(), 'status' => 1, 'model' => 'synthetic', 'platform' => 'synthetic']);
  $plan_chat->save();
  $plan_id = \Drupal::service('uuid')->generate();
  $plan_context = ['version' => 1, 'plan' => ['planId' => 'page-plan', 'goal' => 'Page',
    'audience' => 'Readers', 'tone' => 'Clear', 'type' => 'fullHTML',
    'sections' => [['id' => 'hero', 'type' => 'hero', 'purpose' => 'Welcome']]]];
  $plan_source = '```json{"widget":{"html":"<h1>Original</h1>"}}```';
  $plan_post = ['data' => ['type' => 'node--ai_session', 'id' => $plan_id, 'attributes' => [
    'title' => 'Plan answer', 'session_role' => 'assistant', 'storage_version' => 3,
    'edit_version' => 1, 'storage_mode' => 'inline', 'run_id' => $plan_id,
    'content' => ['value' => $plan_source, 'format' => 'plain_text'],
    'plan_context' => json_encode($plan_context)]]];
  $created_plan = http('/jsonapi/node/ai_session', $bearers['owner'], 'POST', $plan_post);
  check($created_plan['status'] === 201, 'JSON:API creates a v3 message with independent Plan context');
  $writer->update($plan_chat->uuid(), [$plan_id], []);
  $plan_history = '/api/v3/ai/conversations/' . $plan_chat->uuid() . '/sessions';
  $plan_fields = $plan_history . '/' . $plan_id . '/fields';
  $plan_page = http($plan_history, $bearers['owner']);
  check($plan_page['status'] === 200 && $plan_page['data']['storage_version'] === 3 &&
    $plan_page['data']['messages'][0]['fields']['plan_context'] === $plan_context &&
    $plan_page['data']['messages'][0]['fields']['content']['value'] === $plan_source,
    'V3 history restores independent Plan context without embedding it in the source');
  $plan_patch = ['expected_edit_version' => 1, 'fields' => ['plan_context' => $plan_context]];
  $plan_patch['fields']['plan_context']['plan']['goal'] = 'Edited plan';
  \Drupal::state()->set('session_fields_test_denial', ['plan_context', 'view']);
  foreach ([$plan_history, $plan_fields] as $path) {
    $denied = http($path, $bearers['owner']);
    check($denied['status'] === 403 && !isset($denied['data']['session']) &&
      !isset($denied['data']['messages']) && str_contains($denied['cache'], 'no-store'),
      'A denied Plan field cannot leak through history or current snapshots');
  }
  \Drupal::state()->set('session_fields_test_denial', ['plan_context', 'edit']);
  check(http($plan_fields, $bearers['owner'], 'PATCH', $plan_patch)['status'] === 403,
    'V3 CAS checks edit access to the Plan context field');
  \Drupal::state()->set('session_fields_test_denial', NULL);
  $plan_changed = http($plan_fields, $bearers['owner'], 'PATCH', $plan_patch);
  check($plan_changed['status'] === 200 && $plan_changed['data']['status'] === 'updated' &&
    $plan_changed['data']['session']['edit_version'] === 2 &&
    $plan_changed['data']['session']['plan_context'] === $plan_patch['fields']['plan_context'] &&
    $plan_changed['data']['session']['content']['value'] === $plan_source,
    'Authorized Plan edits increment one shared version and retain source bytes');
  $stale_source = http($plan_fields, $bearers['owner'], 'PATCH', ['expected_edit_version' => 1,
    'fields' => ['content' => ['value' => 'Stale source', 'format' => 'plain_text']]]);
  check($stale_source['status'] === 409 &&
    $stale_source['data']['session']['plan_context'] === $plan_patch['fields']['plan_context'],
    'A stale source writer receives the committed Plan before merging');
  $forged_id = \Drupal::service('uuid')->generate();
  $forged = $plan_post;
  $forged['data']['id'] = $forged['data']['attributes']['run_id'] = $forged_id;
  $forged['data']['attributes']['plan_context'] = json_encode($plan_context + ['legacyPlanRunId' => $forged_id]);
  check(http('/jsonapi/node/ai_session', $bearers['owner'], 'POST', $forged)['status'] === 422,
    'New v3 messages cannot manufacture a migrated historical Plan identity');
  $forged['data']['attributes']['plan_context'] = json_encode($plan_context);
  $forged['data']['attributes']['session_role'] = 'user';
  check(http('/jsonapi/node/ai_session', $bearers['owner'], 'POST', $forged)['status'] === 422,
    'A user message cannot acquire assistant Plan context');
  $forged['data']['attributes']['session_role'] = 'assistant';
  $forged['data']['attributes']['storage_version'] = 2;
  unset($forged['data']['attributes']['plan_context']);
  check(http('/jsonapi/node/ai_session', $bearers['owner'], 'POST', $forged)['status'] === 422,
    'A v2 writer cannot create messages after v3 activation');
}
finally {
  \Drupal::state()->set('session_fields_test_denial', NULL);
  $history_config->set('storage_version', 1)->save();
  proc_terminate($server);
  proc_close($server);
}
echo 'Chat history checks: ' . ($GLOBALS['checks'] - $history_checks) . PHP_EOL;
