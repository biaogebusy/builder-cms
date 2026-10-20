<?php

declare(strict_types=1);

/**
 * @file
 * Exercise split messages through the actual entity, JSON:API and revision boundaries.
 */

use Drupal\node\Entity\Node;

if (getenv('XINSHI_ANALYTICS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  \Drupal::database()->getConnectionOptions()['database'] !== '/tmp/analytics-test/site.sqlite') {
  throw new RuntimeException('Use the disposable reference runner only.');
}
$guard_checks = $GLOBALS['checks'];
check(\Drupal::service('xinshi_ai_reference.storage')->version() === 1,
  'Code installation retains the legacy protocol until an explicit cutover');
\Drupal::configFactory()->getEditable('xinshi_ai_reference.settings')->set('storage_version', 2)->save();
\Drupal::currentUser()->setAccount($users['owner']);
$v2_chat = Node::create(['type' => 'conversation', 'title' => 'Split reference fixture',
  'uid' => $users['owner']->id(), 'status' => 1, 'model' => 'synthetic', 'platform' => 'synthetic']);
$v2_chat->save();
$v2_id = \Drupal::service('uuid')->generate();
$v2_reference = ['kind' => 'xinshi-protected-run', 'version' => 1,
  'runId' => $v2_id, 'chatId' => $v2_chat->uuid()];
$v2_protected = Node::create(['type' => 'ai_session', 'uuid' => $v2_id,
  'uid' => $users['owner']->id(), 'status' => 1, 'title' => 'Protected task result',
  'session_role' => 'assistant', 'storage_version' => 2, 'edit_version' => 1,
  'storage_mode' => 'protected_reference', 'run_id' => $v2_id,
  'result_reference' => json_encode($v2_reference)]);
check($v2_protected->validate()->count() === 0, 'A complete split reference validates');
$v2_protected->save();
check(!$v2_protected->access('view', $users['other']), 'Another owner cannot read a split reference');


$snapshots = \Drupal::service('xinshi_ai_reference.snapshots');
$storage = \Drupal::entityTypeManager()->getStorage('node');
$protected_snapshot = $snapshots->validate($v2_protected);
check($protected_snapshot->content === NULL && $protected_snapshot->reasoning_content === NULL &&
  $protected_snapshot->approval_timeline === NULL && $protected_snapshot->knowledge_refs === [],
  'Absent protected fields read back without content or metadata copies');
foreach (['title', 'content', 'reasoning_content', 'summary', 'img', 'style_preset',
  'content_format', 'ui_review', 'approval_timeline', 'knowledge_refs', 'is_error',
  'output_completion', 'total_tokens', 'uiparsing'] as $name) {
  $invalid = $v2_protected->createDuplicate();
  $invalid->set('uuid', $v2_id);
  $value = match ($name) {
    'content' => ['value' => 'SYNTHETIC-PRIVATE-COPY', 'format' => NULL],
    'total_tokens' => 1,
    'is_error', 'uiparsing' => TRUE,
    'knowledge_refs' => [\Drupal::service('uuid')->generate()],
    'ui_review' => '{}',
    'approval_timeline' => '[]',
    'output_completion' => 'complete',
    default => 'SYNTHETIC-PRIVATE-COPY',
  };
  $invalid->set($name, $value);
  check($invalid->validate()->count() > 0, 'Split creation rejects copy in ' . $name);
  rejected(fn() => $invalid->save(), 'Split presave rejects copy in ' . $name);
}
// Unknown presentation fields remain closed even when the v2 contract itself is valid.
field('ai_session', 'future_copy', 'string_long');
$storage->resetCache();
$v2_protected = $storage->load($v2_protected->id());
$invalid = $v2_protected->createDuplicate();
$invalid->set('uuid', $v2_id);
$invalid->set('future_copy', 'SYNTHETIC-PRIVATE-COPY');
rejected(fn() => $invalid->save(), 'Split reference rejects an unknown custom copy field');
foreach ([NULL, '{', '{}', json_encode($v2_reference + ['body' => 'COPY']),
  str_replace('"version":1', '"version":1,"version":1', json_encode($v2_reference)),
  json_encode([...$v2_reference, 'chatId' => \Drupal::service('uuid')->generate()])] as $reference) {
  $invalid = $v2_protected->createDuplicate();
  $invalid->set('uuid', $v2_id);
  $invalid->set('result_reference', $reference);
  check($invalid->validate()->count() > 0, 'Malformed or unbound split locators fail closed');
  check(!$invalid->access('view', $users['other']), 'Corrupt markers still deny another author');
}
foreach (['storage_version', 'edit_version', 'storage_mode', 'run_id', 'result_reference'] as $name) {
  $invalid = clone $v2_protected;
  $invalid->set($name, NULL);
  rejected(fn() => $invalid->save(), 'Existing split references cannot remove ' . $name);
}
$invalid = clone $v2_protected;
foreach (\Drupal\xinshi_ai_reference\ChatSessionSnapshot::NEW_FIELDS as $name) {
  $invalid->set($name, NULL);
}
$invalid->set('content', ['value' => 'Ordinary downgraded text', 'format' => NULL]);
rejected(fn() => $invalid->save(), 'Removing every new field cannot downgrade a protected record');
$invalid = clone $v2_protected;
$invalid->setOwnerId($users['other']->id());
rejected(fn() => $invalid->save(), 'Split reference owner is immutable');
\Drupal::currentUser()->setAccount($users['other']);
rejected(fn() => $v2_protected->save(), 'Another author cannot save an unchanged split reference');
\Drupal::currentUser()->setAccount($users['owner']);
$v2_chat->set('sessions', [['target_id' => $v2_protected->id()]]);
$v2_chat->save();
check(!$v2_chat->get('sessions')->access('view', $users['other']),
  'Split reference relationship linkage is private to the original author');
$wrong_chat = Node::create(['type' => 'conversation', 'title' => 'Wrong split chat',
  'uid' => $users['owner']->id(), 'status' => 1, 'sessions' => [['target_id' => $v2_protected->id()]]]);
rejected(fn() => $wrong_chat->save(), 'Conversation presave rejects split-reference rebinding');
rejected(fn() => $writer->update($otherConversation->uuid(), [$v2_id], []),
  'ConversationWriter rejects split-reference rebinding');
$writer->update($v2_chat->uuid(), [$v2_id], []);
check(TRUE, 'ConversationWriter retains idempotent split-reference membership');

$inline = Node::create(['type' => 'ai_session', 'title' => 'Inline fixture',
  'uid' => $users['owner']->id(), 'status' => 1, 'session_role' => 'assistant',
  'storage_version' => 2, 'edit_version' => 9007199254740991, 'storage_mode' => 'inline',
  'run_created_at_ms' => 1791990000000, 'content' => ['value' => "A😀B\r\n界", 'format' => NULL],
  'reasoning_content' => 'R😀', 'uiparsing' => FALSE, 'is_error' => FALSE,
  'output_completion' => 'incomplete']);
$inline_id = $inline->uuid();
$inline->set('run_id', $inline_id);
$proposal = (object) ['name' => 'retired_write', 'moduleId' => 'retired', 'moduleVersion' => 7,
  'sideEffect' => 'write', 'requiredRoles' => [], 'requiredPermissions' => ['edit draft'],
  'input' => (object) ['emptyObject' => new \stdClass(), 'emptyArray' => [], 'value' => '001'],
  'preview' => (object) ['title' => 'Original proposal', 'summary' => '',
    'target' => (object) ['type' => 'node', 'label' => 'Original target', 'id' => '17']]];
$timeline = [(object) ['contentOffset' => 3, 'reasoningOffset' => 3,
  'approval' => (object) ['runId' => $inline_id, 'approvalId' => 'original',
    'toolCallId' => 'provider-call', 'executionId' => 'original-execution',
    'status' => 'approved', 'executionStatus' => 'uncertain', 'expiresAt' => 1792000000000,
    'decidedAt' => 1791990000000, 'decidedBy' => '42', 'proposal' => $proposal]]];
$inline->set('approval_timeline', json_encode($timeline, JSON_UNESCAPED_UNICODE));
check($inline->validate()->count() === 0, 'Inline v2 supports the exact saved approval contract');
$inline->save();
$storage->resetCache();
$inline = $storage->load($inline->id());
$inline_snapshot = $snapshots->validate($inline);
check($inline_snapshot->edit_version === 9007199254740991 &&
  $inline_snapshot->run_created_at_ms === 1791990000000 && $inline_snapshot->is_error === FALSE &&
  $inline_snapshot->uiparsing === FALSE && $inline_snapshot->content->value === "A😀B\r\n界" &&
  $inline_snapshot->approval_timeline[0]->approval->proposal->input->emptyObject instanceof \stdClass &&
  $inline_snapshot->approval_timeline[0]->approval->proposal->input->emptyArray === [],
  'Real storage preserves safe counters, Unicode, booleans and approved input types');
$empty = $inline->createDuplicate();
$empty->set('run_id', $empty->uuid());
$empty->set('approval_timeline', NULL);
$empty->set('content', ['value' => '', 'format' => 'plain_text']);
$empty->set('reasoning_content', '');
$empty->save();
$storage->resetCache();
$empty_snapshot = $snapshots->validate($storage->load($empty->id()));
check($empty_snapshot->content?->value === '' && $empty_snapshot->content?->format === 'plain_text' &&
  $empty_snapshot->reasoning_content === '', 'Real v2 storage distinguishes empty strings from absent fields');
$inline->setNewRevision(TRUE);
$inline->save();
$storage->resetCache();
$revision = $storage->loadRevision($inline->getRevisionId());
check($snapshots->validate($revision)->edit_version === 9007199254740991,
  'Actual revisions preserve safe-integer edit versions');
foreach (['edit_version' => 1, 'storage_version' => 1,
  'content' => ['value' => 'Changed', 'format' => NULL], 'summary' => 'Changed'] as $name => $value) {
  $invalid = clone $inline;
  $invalid->set($name, $value);
  rejected(fn() => $invalid->save(), 'Non-CAS updates cannot change inline ' . $name);
}
$invalid = clone $inline;
foreach (\Drupal\xinshi_ai_reference\ChatSessionSnapshot::NEW_FIELDS as $name) {
  $invalid->set($name, NULL);
}
rejected(fn() => $invalid->save(), 'Ordinary v2 cannot be downgraded to legacy storage');
\Drupal::configFactory()->getEditable('xinshi_ai_reference.settings')->set('storage_version', 1)->save();
$legacy = Node::create(['type' => 'ai_session', 'title' => 'Legacy fixture',
  'uid' => $users['owner']->id(), 'content' => ['value' => 'Legacy text', 'format' => NULL],
  'session_role' => 'assistant']);
$legacy->save();
$invalid = clone $legacy;
$invalid->set('edit_version', 1);
rejected(fn() => $invalid->save(), 'Partial v2 metadata is rejected on legacy messages');
$legacy->set('content', ['value' => 'Legacy update', 'format' => NULL]);
$legacy->save();
check($legacy->get('content')->value === 'Legacy update', 'Ordinary legacy writes retain their behavior');
\Drupal::configFactory()->getEditable('xinshi_ai_reference.settings')->set('storage_version', 2)->save();
foreach (['{"kind":"xinshi-chat-run","version":1,"content":"x"}',
  '{"k\u0069nd":"xinshi-chat-run","kind":"ordinary"}',
  '{"kind":"xinshi-chat-user",'] as $body) {
  $invalid = $inline->createDuplicate();
  $invalid->set('run_id', $invalid->uuid());
  $invalid->set('approval_timeline', NULL);
  $invalid->set('content', ['value' => $body, 'format' => NULL]);
  check($invalid->validate()->count() > 0, 'V2 rejects managed outer wrappers, including escaped duplicate keys');
}
$plan = $inline->createDuplicate();
$plan->set('run_id', $plan->uuid());
$plan->set('approval_timeline', NULL);
$plan->set('content', ['value' => '{"plan":{"kind":"xinshi-chat-run"}}', 'format' => NULL]);
$plan->save();
check($plan->get('content')->value === '{"plan":{"kind":"xinshi-chat-run"}}',
  'V2 keeps inner Plan text without treating it as an outer wrapper');

// Reading an older revision must not weaken the current protected identity.
$old_revision_id = $v2_protected->getRevisionId();
$v2_protected->setNewRevision(TRUE);
$v2_protected->save();
$old_revision = $storage->loadRevision($old_revision_id);
check(!$old_revision->access('view', $users['other']), 'Historical split revisions retain author access');
$old_revision->set('result_reference', NULL);
rejected(fn() => $old_revision->save(), 'Historical revision saves cannot remove current protection');

// Translation metadata remains common; translated presentation copies still fail validation.
\Drupal::service('module_installer')->install(['language', 'content_translation']);
if (!\Drupal\language\Entity\ConfigurableLanguage::load('fr')) {
  \Drupal\language\Entity\ConfigurableLanguage::createFromLangcode('fr')->save();
}
$translation = $v2_protected->createDuplicate();
$translation->set('uuid', $v2_id);
$translation->addTranslation('fr', ['title' => 'SYNTHETIC-PRIVATE-COPY']);
check($translation->validate()->count() > 0, 'Translated titles cannot introduce a protected copy');
rejected(fn() => $translation->save(), 'Translation presave retains the same protected-reference guard');

// Views bypassing per-row node access still have no protected presentation body to expose.
\Drupal::currentUser()->setAccount($users['other']);
$view_config = \Drupal\views\Entity\View::load('reference_sessions');
$view_displays = $view_config->get('display');
// Deleting the old synthetic summary field removed its handler from this fixture View.
$view_displays['default']['display_options']['fields']['summary'] = [
  'id' => 'summary', 'table' => 'node__summary', 'field' => 'summary', 'plugin_id' => 'field'];
$view_config->set('display', $view_displays)->save();
$view = \Drupal\views\Views::getView('reference_sessions');
$view->setDisplay('default');
$view->execute();
check(isset($view->field['title'], $view->field['summary']),
  'The View retains real title and summary handlers: ' . implode(',', array_keys($view->field)));
$found_split = FALSE;
foreach ($view->result as $row) {
  if ((string) $row->nid === (string) $v2_protected->id()) {
    $found_split = TRUE;
    check($view->field['title']->getValue($row) === 'Protected task result' &&
      !$view->field['summary']->getValue($row), 'Field-based Views expose only fixed split title and empty summary');
  }
}
check($found_split, 'The actual View exercised the split protected row');
\Drupal::currentUser()->setAccount($users['owner']);
$writer->update($v2_chat->uuid(), [$inline_id], []);
node_access_rebuild();
$role = \Drupal\user\Entity\Role::load('reference_owner');
$role->grantPermission('view ai_session revisions')->save();
\Drupal::service('router.builder')->rebuild();
\Drupal::service('kernel')->invalidateContainer();
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
  $base = '/jsonapi/node/ai_session';
  $created_id = \Drupal::service('uuid')->generate();
  $created = http($base, $bearers['owner'], 'POST', ['data' => [
    'type' => 'node--ai_session', 'id' => $created_id, 'attributes' => [
      'title' => 'Protected task result', 'session_role' => 'assistant',
      'storage_version' => 2, 'edit_version' => 1, 'storage_mode' => 'protected_reference',
      'run_id' => $created_id, 'result_reference' => json_encode([...$v2_reference, 'runId' => $created_id]),
    ],
  ]]);
  check($created['status'] === 201, 'JSON:API creates a complete split locator without heavy AI dependencies');
  check(http($base, $bearers['owner'], 'POST', ['data' => [
    'type' => 'node--ai_session', 'attributes' => [
      'title' => 'Invalid partial', 'session_role' => 'assistant', 'edit_version' => 1,
    ],
  ]])['status'] === 422, 'JSON:API rejects incomplete new-field creation');
  foreach ([$bearers['owner'] => 200, $bearers['other'] => 403] as $token => $status) {
    check(http($base . '/' . $v2_id, $token)['status'] === $status, 'Split single-resource author access');
    $historical = http($base . '/' . $v2_id . '?resourceVersion=id:' . $old_revision_id, $token);
    check($historical['status'] === $status,
      'Split historical resource author access: ' . $historical['status']);
  }
  check(http($base . '/' . $v2_id)['status'] === 401,
    'Anonymous split reads retain JSON:API authentication denial');
  $list = http($base . '?filter[id]=' . $v2_id, $bearers['other']);
  check($list['status'] === 200 && $list['data']['data'] === [], 'Split collections omit another author reference');
  $chat_path = '/jsonapi/node/conversation/' . $v2_chat->uuid();
  foreach (['/relationships/sessions', '/sessions'] as $suffix) {
    check(http($chat_path . $suffix, $bearers['other'])['status'] === 403, 'Split relationship and related reads deny another author');
  }
  $included = http($chat_path . '?include=sessions', $bearers['other']);
  check($included['status'] === 200 && empty($included['data']['included']) &&
    !isset($included['data']['data']['relationships']['sessions']), 'Included split locators cannot bypass field access');
  foreach (['summary' => 'COPY', 'storage_mode' => 'inline', 'storage_version' => NULL,
    'result_reference' => NULL, 'edit_version' => 2] as $name => $value) {
    check(http($base . '/' . $v2_id, $bearers['owner'], 'PATCH', ['data' => [
      'type' => 'node--ai_session', 'id' => $v2_id, 'attributes' => [$name => $value]]])['status'] === 422,
      'Direct JSON:API cannot bypass copy protection or CAS with ' . $name);
  }
  check(http('/jsonapi/node/conversation/' . $otherConversation->uuid() . '/relationships/sessions',
    $bearers['owner'], 'POST', ['data' => [['type' => 'node--ai_session', 'id' => $v2_id]]])['status'] === 422,
    'JSON:API relationship writes cannot rebind split references');
  $jar = $fixture . '/split-owner.cookie';
  $login = http('/user/login?_format=json', NULL, 'POST', [
    'name' => 'reference-owner', 'pass' => 'test-only-reference'], $jar);
  check($login['status'] === 200 && http($base . '/' . $v2_id, NULL, 'GET', NULL, $jar)['status'] === 200,
    'Cookie author can read split references');
  $patch = ['data' => ['type' => 'node--ai_session', 'id' => $v2_id, 'attributes' => ['edit_version' => 1]]];
  check(http($base . '/' . $v2_id, NULL, 'PATCH', $patch, $jar)['status'] === 403,
    'Split Cookie writes require CSRF');
  check(http($base . '/' . $v2_id, NULL, 'PATCH', $patch, $jar, $login['data']['csrf_token'])['status'] === 200,
    'Unchanged split Cookie writes retain normal entity access and CSRF');
  foreach ([$inline_id, $v2_id] as $id) {
    $path = '/api/v3/ai/conversations/' . $v2_chat->uuid() . '/sessions/' . $id . '/content';
    foreach ([hash('sha256', $id === $inline_id ? "A😀B\r\n界" : ''), str_repeat('0', 64)] as $hash) {
      check(http($path, $bearers['owner'], 'PATCH', ['expectedHash' => $hash, 'content' => 'COPY'])['status'] === 422,
        'Content-only writer rejects v2 before both matching and stale hash responses');
    }
  }
  $read = http($base . '/' . $inline_id, $bearers['owner']);
  check($read['data']['data']['attributes']['content']['value'] === "A😀B\r\n界",
    'Rejected old-writer requests leave inline content unchanged');
}
finally {
  proc_terminate($server);
  proc_close($server);
}
echo 'Split guard checks: ' . ($GLOBALS['checks'] - $guard_checks) . PHP_EOL;
