<?php

declare(strict_types=1);

/**
 * @file
 * Synthetic fixtures and independent assertions for the actual offline Drush commands.
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Xinshi\ChatMigration\Archive;

if (getenv('XINSHI_ANALYTICS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  !\Drupal::hasContainer() ||
  \Drupal::database()->getConnectionOptions()['database'] !== '/tmp/analytics-test/site.sqlite') {
  throw new RuntimeException('Use the disposable reference runner only.');
}
umask(0077);
$root = '/tmp/analytics-test/chat-migration';
foreach (['Archive', 'Source'] as $class) {
  require_once '/app/scripts/chat-session-migration/' . $class . '.php';
}
require_once '/app/docroot/modules/custom/xinshi_ai/src/Service/ChatSessionFields.php';
require_once '/app/docroot/modules/custom/xinshi_ai/src/Service/ChatSessionFieldScan.php';
$db = \Drupal::database();
$storage = \Drupal::entityTypeManager()->getStorage('node');
$users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => 'reference-owner']);
$owner = reset($users);
\Drupal::currentUser()->setAccount($owner);
$operation = $extra[0] ?? '';
$assert = static function (bool $ok, string $label): void {
  if (!$ok) {
    throw new RuntimeException($label);
  }
  echo 'PASS: ' . $label . PHP_EOL;
};

if ($operation === 'setup') {
  mkdir($root, 0700);
  // These bundles belong exclusively to earlier tests on the fixed disposable SQLite site.
  $storage->delete($storage->loadByProperties(['type' => ['conversation', 'ai_session']]));
  FieldStorageConfig::loadByName('node', 'future_copy')?->delete();
  // The outer-field batch starts before the independently installed Plan field.
  FieldStorageConfig::loadByName('node', 'plan_context')?->delete();
  field_purge_batch(1000);
  \Drupal::configFactory()->getEditable('xinshi_ai_reference.settings')->set('storage_version', 1)->save();
  $chat = Node::create(['type' => 'conversation', 'title' => 'Offline migration',
    'uid' => $owner->id(), 'status' => 1]);
  $chat->save();
  $ids = [];
  $expected = [];
  for ($index = 0; $index < 31; $index++) {
    $message = Node::create(['type' => 'ai_session', 'title' => 'Migration ' . $index,
      'uid' => $owner->id(), 'status' => 1, 'session_role' => 'assistant',
      'reasoning_content' => "Reason😀\r\n", 'total_tokens' => 17]);
    $plan = '{ "planId":"plan-' . $index . '", "goal":"页面😀", "audience":"Readers",' .
      ' "tone":"Clear", "type":"fullHTML", "sections":[{"id":"hero","type":"hero","purpose":"Welcome"}] }';
    if ($index === 1) {
      $plan = '{"planId":"plan-1","goal":"Old blueprint","type":"fullHTML","steps":[]}';
    }
    $summary = '稿件😀' . ($index === 0 ? str_repeat('x', 70000) : '');
    $body = "\u{FEFF}```json\r\n{\"summary\":\"" . $summary . "\",\"widget\":{\"html\":\"<h1>Original</h1>\"},\"plan\":" .
      $plan . ',"planId":"plan-' . $index . '","planRunId":"' . $message->uuid() . '"}' . "\r\n```\u{00A0}";
    if ($index === 2 || $index === 3) {
      $body = '{"plan":' . $plan . ',"summary":"Keep original source",' .
        ($index === 2 ? '"planReviews":[{"round":1,"ok":true}]' : '"isError":true') . '}';
    }
    if ($index === 4) {
      $body = "```json\n{\"summary\":\"Unfinished ordinary code\"}";
    }
    $envelope = ['kind' => 'xinshi-chat-run', 'version' => 1, 'runId' => $message->uuid(),
      'content' => $body, 'runCreatedAt' => 1792000000000, 'outputCompletion' => 'complete'];
    $message->set('content', ['value' => Archive::json($envelope), 'format' => 'plain_text']);
    if ($index === 0) {
      $message->addTranslation('fr', ['title' => 'French presentation']);
    }
    $message->save();
    $message->setNewRevision(TRUE);
    $message->setRevisionLogMessage('Retain original revisions');
    $message->save();
    $ids[] = $message->id();
    $expected[$message->id()] = ['uuid' => $message->uuid(), 'body' => $body, 'plan' => $plan,
      'opaque' => $index >= 1 && $index <= 4,
      'envelope' => Archive::json($envelope), 'vid' => $message->getRevisionId()];
  }
  $protected_id = \Drupal::service('uuid')->generate();
  $reference = ['kind' => 'xinshi-protected-run', 'version' => 1,
    'runId' => $protected_id, 'chatId' => $chat->uuid()];
  $protected = Node::create(['type' => 'ai_session', 'uuid' => $protected_id,
    'uid' => $owner->id(), 'status' => 1, 'title' => 'Protected task result',
    'session_role' => 'assistant', 'content' => ['value' => Archive::json($reference), 'format' => NULL]]);
  $protected->save();
  $chat->set('sessions', array_map(static fn($id) => ['target_id' => $id], [...$ids, $protected->id()]));
  $chat->save();
  \Drupal::state()->set('system.maintenance_mode', TRUE);
  $site = \Drupal::config('system.site')->get('uuid');
  Archive::publish($root . '/fixture.json', ['site' => $site, 'chat' => $chat->uuid(),
    'messages' => $expected, 'protected' => (string) $protected->id(), 'reference' => $reference]);
  echo Archive::json(['site' => $site]) . PHP_EOL;
  return;
}

$fixture = Archive::manifest($root . '/fixture.json');
if ($operation === 'fail' || $operation === 'unfail') {
  $db->query('DROP TRIGGER IF EXISTS chat_migration_test_failure');
  if ($operation === 'fail') {
    $table = ($extra[1] ?? '') === 'plan' ? 'node__plan_context' : 'node__storage_version';
    $id = (int) array_keys($fixture['messages'])[27];
    $db->query("CREATE TRIGGER chat_migration_test_failure BEFORE INSERT ON {{$table}} " .
      "WHEN NEW.entity_id = $id BEGIN SELECT RAISE(ABORT, 'Synthetic batch failure'); END",
      [], ['allow_delimiter_in_query' => TRUE]);
  }
  return;
}

if ($operation === 'verify') {
  $version = (int) ($extra[1] ?? 0);
  $assert(\Drupal::service('xinshi_ai_reference.storage')->version() === $version,
    'Activation selects exactly version ' . $version);
  $assert((bool) \Drupal::state()->get('system.maintenance_mode'), 'Migration retains maintenance mode');
  $storage->resetCache();
  foreach ($fixture['messages'] as $id => $expected) {
    $node = $storage->load($id);
    $assert($node->uuid() === $expected['uuid'] &&
      (string) $node->getRevisionId() === (string) $expected['vid'], 'UUID and revision remain unchanged');
    $assert($node->get('reasoning_content')->value === "Reason😀\r\n", 'Reasoning bytes remain unchanged');
    if ($version < 3) {
      $assert($node->get('content')->value === $expected[$version === 1 ? 'envelope' : 'body'],
        'Original body bytes survive version ' . $version);
    }
    elseif ($expected['opaque']) {
      $snapshot = \Drupal::service('xinshi_ai_reference.snapshots')->validate($node, [$fixture['chat']]);
      $assert($snapshot->plan_context === NULL && $snapshot->content->value === $expected['body'] &&
        $snapshot->is_error === NULL, 'Accepted legacy text stays exact without an invented Plan or error flag');
    }
    else {
      $context = $node->get('plan_context')->value;
      $assert(str_contains($context, $expected['plan']), 'Plan JSON member bytes remain unchanged');
      $snapshot = \Drupal::service('xinshi_ai_reference.snapshots')->validate($node, [$fixture['chat']]);
      $assert($snapshot->plan_context->legacyPlanRunId === $expected['uuid'] &&
        $snapshot->run_id === $expected['uuid'], 'Historical Plan identity and generation identity remain distinct');
      $assert(!str_contains($snapshot->content->value, 'planId') &&
        str_contains($snapshot->content->value, '<h1>Original</h1>'), 'Only Plan context leaves the generated body');
    }
  }
  if ($version > 1) {
    $protected = \Drupal::service('xinshi_ai_reference.snapshots')->validate(
      $storage->load($fixture['protected']), [$fixture['chat']]);
    $assert($protected->content === NULL && $protected->storage_mode === 'protected_reference',
      'Protected references stay copy-free after migration');
  }
  $first = $storage->load(array_key_first($fixture['messages']));
  $assert($first->getTranslation('fr')->label() === 'French presentation',
    'Translated presentation remains unchanged');
  return;
}

if ($operation === 'v3-cas') {
  $id = array_key_first($fixture['messages']);
  $node = $storage->load($id);
  $snapshots = \Drupal::service('xinshi_ai_reference.snapshots');
  $before = $snapshots->validate($node, [$fixture['chat']]);
  $writer = \Drupal::service('xinshi_ai_reference.fields_writer');
  $changed = $writer->update($fixture['chat'], $node->uuid(), $before->edit_version,
    (object) ['summary' => 'Updated after cutover']);
  $assert($changed['status'] === 'updated' &&
    Archive::json($changed['session']->plan_context) === Archive::json($before->plan_context),
    'V3 CAS preserves migrated Plan context and advances one version');
  $invalid = clone $before->plan_context;
  unset($invalid->legacyPlanRunId);
  try {
    $writer->update($fixture['chat'], $node->uuid(), $changed['session']->edit_version,
      (object) ['plan_context' => $invalid]);
    throw new RuntimeException('Historical identity unexpectedly changed');
  }
  catch (\Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException) {
    $assert(TRUE, 'V3 CAS cannot remove historical Plan identity');
  }
  return;
}
throw new RuntimeException('Unknown synthetic fixture operation');
