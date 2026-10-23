<?php

declare(strict_types=1);

/**
 * @file
 * Atomic v2 field writes, rollback, revisions and actual OAuth HTTP on a synthetic site.
 */

use Drupal\node\Entity\Node;
use Drupal\xinshi_ai_reference\ChatSessionFormat;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

if (getenv('XINSHI_ANALYTICS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  \Drupal::database()->getConnectionOptions()['database'] !== '/tmp/analytics-test/site.sqlite') {
  throw new RuntimeException('Use the disposable reference runner only.');
}
$cas_checks = $GLOBALS['checks'];
\Drupal::configFactory()->getEditable('xinshi_ai_reference.settings')->set('storage_version', 1)->save();
\Drupal::currentUser()->setAccount($users['owner']);
$fields_writer = \Drupal::service('xinshi_ai_reference.fields_writer');
$snapshots = \Drupal::service('xinshi_ai_reference.snapshots');
$storage = \Drupal::entityTypeManager()->getStorage('node');
$legacy_empty = Node::create(['type' => 'ai_session', 'title' => 'Legacy empty strings',
  'uid' => $users['owner']->id(), 'session_role' => 'assistant',
  'content' => ['value' => '', 'format' => NULL], 'reasoning_content' => '']);
$legacy_empty->save();
$storage->resetCache();
$legacy_empty = $storage->load($legacy_empty->id());
check($legacy_empty->get('content')->getValue() === [] &&
  $legacy_empty->get('reasoning_content')->getValue() === [],
  'Cached field plugins preserve legacy empty-string filtering');
$ordinary_empty = Node::create(['type' => 'conversation', 'title' => 'Ordinary empty string',
  'uid' => $users['owner']->id(), 'model' => '']);
check($ordinary_empty->get('model')->isEmpty(), 'Other bundles retain core string emptiness');
\Drupal::configFactory()->getEditable('xinshi_ai_reference.settings')->set('storage_version', 2)->save();
$message = Node::create(['type' => 'ai_session', 'title' => 'Editable message',
  'uid' => $users['owner']->id(), 'status' => 1, 'session_role' => 'assistant',
  'storage_version' => 2, 'edit_version' => 1, 'storage_mode' => 'inline',
  'content' => ['value' => "Source😀\r\n", 'format' => 'plain_text'],
  'reasoning_content' => 'Original reasoning']);
$message->set('run_id', $message->uuid());
$message->set('future_copy', '');
check($message->get('future_copy')->isEmpty(), 'Unselected v2 fields retain core string emptiness');
$message->save();
$original_revision = $message->getRevisionId();
$unchanged_old_revision = clone $message;
$message->setNewRevision(TRUE);
$message->save();
rejected(fn() => $unchanged_old_revision->save(),
  'Even an unchanged snapshot must match the current locked revision before a direct save');
$writer->update($v2_chat->uuid(), [$message->uuid()], []);
$update_fields = fn(int $version, array $fields) =>
  $fields_writer->update($v2_chat->uuid(), $message->uuid(), $version, (object) $fields);
$fresh = static function () use ($storage, $message, $snapshots): \stdClass {
  $storage->resetCache();
  return $snapshots->validate($storage->load($message->id()));
};
$expect_error = static function (callable $callback, int $status, string $label): void {
  try {
    $callback();
  }
  catch (HttpExceptionInterface $error) {
    check($error->getStatusCode() === $status, $label . ': ' . $error->getStatusCode());
    return;
  }
  throw new RuntimeException('Unexpected success: ' . $label);
};
$first_patch = ['content' => (object) ['value' => "Changed😀\r\n", 'format' => 'plain_text'],
  'summary' => 'New summary', 'is_error' => FALSE];
$first = $update_fields(1, $first_patch);
check($first['status'] === 'updated' && $first['session']->edit_version === 2 &&
  $first['session']->content->value === "Changed😀\r\n" && $first['session']->summary === 'New summary' &&
  $first['session']->is_error === FALSE && $first['session']->reasoning_content === 'Original reasoning',
  'Multi-field CAS changes only the patch and increments the shared version once');
check($snapshots->validate($storage->loadRevision($original_revision))->content->value === "Source😀\r\n" &&
  $snapshots->validate($storage->loadRevision($original_revision))->edit_version === 1,
  'A successful CAS preserves the original revision and version');
$old_revision = $storage->loadRevision($original_revision);
rejected(fn() => $old_revision->save(), 'Saving a historical revision cannot restore an old CAS version');
$saved_revision = $storage->load($message->id())->getRevisionId();
$retry = $update_fields(1, $first_patch);
check($retry['status'] === 'unchanged' && $retry['session']->edit_version === 2 &&
  $storage->load($message->id())->getRevisionId() === $saved_revision,
  'A lost-response retry confirms current fields without another write or revision');
$conflict = $update_fields(1, ['summary' => 'Stale summary']);
check($conflict['status'] === 'conflict' && $conflict['session']->summary === 'New summary' &&
  $fresh()->content->value === "Changed😀\r\n", 'A stale partial patch returns current fields without overwrite');
check($update_fields(3, $first_patch)['status'] === 'conflict',
  'A future expected version is not accepted even when fields match');
$review_report = (object) ['version' => 1, 'type' => 'fullHTML', 'sourceHash' => str_repeat('a', 64),
  'status' => 'unavailable', 'summary' => 'Vision unavailable', 'issues' => [], 'captures' => [],
  'limitations' => [], 'execution' => (object) ['version' => 1, 'status' => 'completed',
    'checks' => [(object) ['id' => 'mobile-interaction-0', 'viewport' => 'mobile',
      'kind' => 'interaction', 'status' => 'failed', 'code' => 'step-failed', 'count' => 1,
      'label' => 'Quote calculation', 'step' => 3]]]];
$review = (object) ['version' => 1, 'report' => $review_report,
  'lastReviewRun' => (object) ['id' => \Drupal::service('uuid')->generate(), 'createdAt' => 1792000000000]];
$review_result = $update_fields(2, ['ui_review' => $review]);
check($review_result['session']->run_id === $message->uuid() &&
  $review_result['session']->ui_review->lastReviewRun->id === $review->lastReviewRun->id &&
  $review_result['session']->content->value === "Changed😀\r\n",
  'Review-only changes retain the winning source and original generation identity');
check($fresh()->ui_review->report->execution->checks[0]->step === 3,
  'Execution feedback survives field persistence independently of the unavailable visual verdict');
$ordered_review = (object) ['lastReviewRun' => (object) [
  'createdAt' => 1792000000000, 'id' => $review->lastReviewRun->id], 'report' => $review_report, 'version' => 1];
check($update_fields(2, ['ui_review' => $ordered_review])['status'] === 'unchanged',
  'Retry comparison ignores object key order while preserving JSON types');
foreach ([['status', 'passed'], ['count', '1'], ['count', 0], ['step', 13],
  ['kind', 'script'], ['viewport', 'desktop'], ['code', 'invented'], ['browserLog', 'private']] as [$key, $value]) {
  $bad_review = ChatSessionFormat::decode(ChatSessionFormat::encode($review));
  $bad_review->report->execution->checks[0]->$key = $value;
  $expect_error(fn() => $update_fields(3, ['ui_review' => $bad_review]), 422,
    'Execution feedback rejects contradictory types, codes or copied logs: ' . $key);
}
foreach (['edit_version' => 8, 'storage_version' => 1, 'storage_mode' => 'protected_reference',
  'run_id' => $message->uuid(), 'run_created_at_ms' => 1, 'session_role' => 'user',
  'result_reference' => NULL, 'uid' => $users['other']->id(), 'unknown' => TRUE] as $name => $value) {
  $expect_error(fn() => $update_fields(3, [$name => $value]), 422, 'Client cannot edit ' . $name);
}
foreach ([['is_error' => 'false'], ['total_tokens' => '3'], ['content' => (object) ['value' => 'Missing format']],
  ['content' => (object) ['value' => '{"kind":"xinshi-chat-run","version":1}', 'format' => NULL]],
  ['ui_review' => (object) []], ['knowledge_refs' => [\Drupal::service('uuid')->generate()]],
  ['content' => (object) ['value' => str_repeat('x', ChatSessionFormat::CONTENT_BYTES + 1), 'format' => NULL]],
  []] as $bad_patch) {
  $expect_error(fn() => $update_fields(3, $bad_patch), 422, 'Invalid patch types, shape or limits fail closed');
}
$expect_error(fn() => $update_fields(3, ['content' => (object) [
  'value' => 'Untrusted HTML', 'format' => 'full_html']]), 403, 'Text formats require current format access');
check($fresh()->edit_version === 3, 'Rejected updates leave the complete version unchanged');
foreach ([['summary', 'view'], ['summary', 'edit'], ['edit_version', 'edit'], ['sessions', 'view']] as $denial) {
  \Drupal::state()->set('session_fields_test_denial', $denial);
  $expect_error(fn() => $update_fields(1, ['summary' => 'New summary']), 403,
    'Field denial precedes stale retry and conflict disclosure for ' . implode(':', $denial));
  \Drupal::state()->delete('session_fields_test_denial');
}
$before_failure = ChatSessionFormat::encode($fresh());
$revision_count = \Drupal::database()->select('node_revision', 'r')->condition('nid', $message->id())
  ->countQuery()->execute()->fetchField();
foreach (['session_fields_test_fail', 'session_fields_test_mutate'] as $failure) {
  \Drupal::state()->set($failure, TRUE);
  try {
    $update_fields(3, ['summary' => 'Must roll back', 'content' => (object) ['value' => '', 'format' => NULL]]);
    throw new RuntimeException('Unexpected save success.');
  }
  catch (Throwable $error) {
    check($error->getMessage() !== 'Unexpected save success.', 'Save failure is propagated: ' . $failure);
  }
  finally {
    // Flush the reset before the independent HTTP process reads the shared state cache.
    \Drupal::state()->set($failure, FALSE);
  }
  check(ChatSessionFormat::encode($fresh()) === $before_failure &&
    \Drupal::database()->select('node_revision', 'r')->condition('nid', $message->id())
      ->countQuery()->execute()->fetchField() === $revision_count,
    'Failed save rolls back fields, version and the new revision: ' . $failure);
}
$direct = $storage->load($message->id());
$direct->set('summary', 'Bypass after failure');
rejected(fn() => $direct->save(), 'CAS authorization never survives beyond the exact write');
$empty = $update_fields(3, ['content' => (object) ['value' => '', 'format' => NULL], 'reasoning_content' => '']);
check($empty['session']->content->value === '' && $fresh()->reasoning_content === '' &&
  $empty['session']->edit_version === 4, 'Empty strings survive the complete CAS round trip');
$expect_error(fn() => $fields_writer->update($v2_chat->uuid(), $inline_id, 9007199254740991,
  (object) ['summary' => 'Overflow']), 422, 'Version overflow cannot persist an unsafe integer');
$expect_error(fn() => $fields_writer->update($v2_chat->uuid(), $v2_id, 1,
  (object) ['summary' => 'Protected copy']), 422, 'Protected references remain copy-free through CAS');
check($fields_writer->update($v2_chat->uuid(), $v2_id, 1,
  (object) ['title' => 'Protected task result'])['status'] === 'unchanged',
  'An authorized unchanged protected reference creates no revision');
$user_message = Node::create(['type' => 'ai_session', 'title' => 'User message',
  'uid' => $users['owner']->id(), 'status' => 1, 'session_role' => 'user',
  'storage_version' => 2, 'edit_version' => 1, 'storage_mode' => 'inline']);
$user_message->addTranslation('fr', ['title' => 'French user message']);
$user_message->save();
$writer->update($v2_chat->uuid(), [$user_message->uuid()], []);
$knowledge = [\Drupal::service('uuid')->generate(), \Drupal::service('uuid')->generate()];
$knowledge_result = $fields_writer->update($v2_chat->uuid(), $user_message->uuid(), 1,
  (object) ['knowledge_refs' => $knowledge]);
check($knowledge_result['session']->knowledge_refs === $knowledge,
  'Ordered knowledge UUIDs are retained even when their source is unavailable');
$storage->resetCache();
$translated = $storage->load($user_message->id())->getTranslation('fr');
check($translated->label() === 'French user message' && $snapshots->validate($translated)->edit_version === 2,
  'CAS preserves translated presentation with one shared message version');
\Drupal::state()->set('session_fields_test_translation', 'fr');
$expect_error(fn() => $fields_writer->update($v2_chat->uuid(), $user_message->uuid(), 2,
  (object) ['knowledge_refs' => []]), 403, 'Shared fields require edit access on affected translations');
\Drupal::state()->set('session_fields_test_translation', NULL);
$scoped_token = $repo->getNewToken($client, [], (string) $users['owner']->id());
$scoped_token->setIdentifier(bin2hex(random_bytes(24)));
$scoped_token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
$scoped_token->setPrivateKey(new \League\OAuth2\Server\CryptKey('/tmp/analytics-test/private.pem'));
$repo->persistNewAccessToken($scoped_token);
$scoped_bearer = $scoped_token->convertToJWT()->toString();
node_access_rebuild();
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
  $path = '/api/v3/ai/conversations/' . $v2_chat->uuid() . '/sessions/' . $message->uuid() . '/fields';
  $payload = ['expected_edit_version' => 4, 'fields' => ['summary' => 'HTTP summary', 'total_tokens' => 0]];
  foreach ([$bearers['other'], NULL] as $token) {
    $denied = http($path, $token, 'PATCH', $payload);
    check($denied['status'] === 403 && !isset($denied['data']['session']), 'Unauthenticated or non-owner CAS returns no DTO');
    check(str_contains($denied['cache'], 'no-store'), 'CAS authentication errors cannot be cached');
  }
  check(http($path, $scoped_bearer, 'PATCH', $payload)['status'] === 403,
    'CAS retains effective OAuth scope instead of reloading the full owner account');
  $jar = $fixture . '/cas-owner.cookie';
  $login = http('/user/login?_format=json', NULL, 'POST', [
    'name' => 'reference-owner', 'pass' => 'test-only-reference'], $jar);
  check($login['status'] === 200, 'CAS cookie fixture logged in');
  check(http($path, NULL, 'PATCH', $payload, $jar, $login['data']['csrf_token'])['status'] === 403,
    'CAS rejects Cookie writes even with a valid CSRF token');
  check(http($path, $bearers['other'], 'PATCH', $payload, $jar, $login['data']['csrf_token'])['status'] === 403,
    'Cookie ownership cannot elevate another OAuth principal');
  $wrong_path = '/api/v3/ai/conversations/' . $otherConversation->uuid() . '/sessions/' . $message->uuid() . '/fields';
  check(http($wrong_path, $bearers['owner'], 'PATCH', $payload)['status'] === 403,
    'The fields route requires actual conversation membership');
  $changed = http($path, $bearers['owner'], 'PATCH', $payload);
  check($changed['status'] === 200 && $changed['data']['status'] === 'updated' &&
    $changed['data']['session']['edit_version'] === 5 && $changed['data']['session']['total_tokens'] === 0 &&
    $changed['data']['session']['content']['value'] === '' && $changed['data']['session']['reasoning_content'] === '',
    'Actual OAuth PATCH atomically commits the field patch');
  $retry = http($path, $bearers['owner'], 'PATCH', $payload);
  check($retry['status'] === 200 && $retry['data']['status'] === 'unchanged' &&
    $retry['data']['session']['edit_version'] === 5, 'Actual HTTP retry preserves the committed version');
  $conflict = http($path, $bearers['owner'], 'PATCH', [
    'expected_edit_version' => 4, 'fields' => ['summary' => 'Stale HTTP summary']]);
  check($conflict['status'] === 409 && $conflict['data']['status'] === 'conflict' &&
    $conflict['data']['session']['summary'] === 'HTTP summary', 'Actual HTTP conflict returns the authorized current DTO');
  foreach ([$changed, $retry, $conflict] as $response) {
    check(str_contains($response['cache'], 'no-store'), 'Success, retry and conflict are private/no-store');
  }
  foreach ([['expected_edit_version' => '5', 'fields' => ['summary' => 'bad']],
    $payload + ['extra' => TRUE], ['expected_edit_version' => 5, 'fields' => []]] as $invalid) {
    check(http($path, $bearers['owner'], 'PATCH', $invalid)['status'] === 400,
      'HTTP request shape rejects coercion, extra keys and array patches');
  }
  foreach (['{"expected_edit_version":5,"expected_edit_version":5,"fields":{"summary":"x"}}',
    '{"expected_edit_version":5,"fields":{"summary":"x","summary":"y"}}',
    '{"expected_edit_version":5,"fields":'] as $raw) {
    check(http($path, $bearers['owner'], 'PATCH', $raw)['status'] === 400,
      'The HTTP decoder rejects duplicate keys and malformed JSON');
  }
  check(http($path, $bearers['owner'], 'PATCH', [
    'expected_edit_version' => 5, 'fields' => ['edit_version' => 6]])['status'] === 422,
    'HTTP cannot choose the next server version');
  $jsonapi = '/jsonapi/node/ai_session/' . $message->uuid();
  check(http($jsonapi, $bearers['owner'], 'PATCH', ['data' => ['type' => 'node--ai_session',
    'id' => $message->uuid(), 'attributes' => ['summary' => 'Bypass', 'edit_version' => 6]]])['status'] === 422,
    'Direct JSON:API still cannot bypass CAS after a successful write');
  $missing_path = '/api/v3/ai/conversations/' . $v2_chat->uuid() . '/sessions/' .
    \Drupal::service('uuid')->generate() . '/fields';
  check(http($missing_path, $bearers['owner'], 'PATCH', $payload)['status'] === 404,
    'CAS never recreates a deleted or missing message');
}
finally {
  proc_terminate($server);
  proc_close($server);
}
echo 'Session fields CAS checks: ' . ($GLOBALS['checks'] - $cas_checks) . PHP_EOL;
\Drupal::configFactory()->getEditable('xinshi_ai_reference.settings')->set('storage_version', 1)->save();
