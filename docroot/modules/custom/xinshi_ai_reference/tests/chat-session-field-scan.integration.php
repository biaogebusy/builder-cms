<?php

declare(strict_types=1);

/**
 * @file
 * Verify configuration scans after preparing a common-schema disposable fixture.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\xinshi_ai\Service\ChatSessionFields;
use Drupal\xinshi_ai\Service\ChatSessionFieldScan;

if (getenv('XINSHI_ANALYTICS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  \Drupal::database()->getConnectionOptions()['database'] !== '/tmp/analytics-test/site.sqlite') {
  throw new RuntimeException('Use the disposable reference runner only.');
}
require_once dirname(__DIR__, 2) . '/xinshi_ai/src/Service/ChatSessionFieldScan.php';
set_exception_handler(static function (Throwable $error): void {
  fwrite(STDERR, $error->getMessage() . PHP_EOL . $error->getTraceAsString() . PHP_EOL);
  exit(1);
});
$initial_checks = $GLOBALS['checks'];

// The reference fixture uses deliberately different types; replace them only in this synthetic site.
foreach (['content', 'summary', 'future_copy'] as $name) {
  FieldStorageConfig::loadByName('node', $name)->delete();
}
field_purge_batch(1000);
foreach (ChatSessionFieldScan::sourceDefinitions() as $bundle => $definitions) {
  $type = NodeType::load($bundle);
  $type->setNewRevision(TRUE);
  $type->save();
  foreach ($definitions as $name => $definition) {
    if (!FieldConfig::loadByName('node', $bundle, $name)) {
      field($bundle, $name, $definition['type'], $definition['settings'] ?? [],
        $definition['cardinality'] ?? 1);
    }
    $field_config = FieldConfig::loadByName('node', $bundle, $name);
    $field_config->setRequired($definition['required'] ?? FALSE);
    $field_config->setTranslatable(FALSE);
    if ($name === 'sessions') {
      $field_config->setSetting('handler', 'default:node');
      $field_config->setSetting('handler_settings', ['target_bundles' => ['ai_session' => 'ai_session']]);
    }
    $field_config->save();
  }
}
$db = \Drupal::database();
$state_hash = static function () use ($db): string {
  $state = [];
  foreach (['config', 'node', 'node_field_data', 'node_revision', 'node_field_revision',
    'node__sessions', 'node_revision__sessions', 'node__content', 'node_revision__content'] as $table) {
    $rows = $db->select($table, 't')->fields('t')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $rows = array_map(static fn(array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows);
    sort($rows, SORT_STRING);
    $state[$table] = $rows;
  }
  return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
};
$fields = \Drupal::service('entity_field.manager');
$resources = \Drupal::service('jsonapi.resource_type.repository');
$scan = static fn(): array => (new ChatSessionFieldScan($fields,
  \Drupal::entityTypeManager(), $resources))->scan();
$before = $state_hash();
$baseline = $scan();
check($baseline['matches'] && $baseline['differences'] === [],
  'The common field schema matches: ' . json_encode($baseline['differences']));
check($baseline === $scan(), 'Repeated scans produce deterministic reports and fingerprints');
check($state_hash() === $before, 'Scanning does not write configuration, content, revisions or relationships');
check(!str_contains(json_encode($baseline), 'Original body'), 'Reports contain configuration without message text');
$difference = static function (array $report, string $code, ?string $name): bool {
  foreach ($report['differences'] as $item) {
    if ($item['code'] === $code && $item['field'] === $name) {
      return !$report['matches'];
    }
  }
  return FALSE;
};

// JSON:API aliases are tested against actual resource objects without saving site overrides.
$repository = new class($resources) implements ResourceTypeRepositoryInterface {
  public array $overrides = [];
  public function __construct(private readonly ResourceTypeRepositoryInterface $original) {}
  public function all() { return $this->original->all(); }
  public function get($entity_type_id, $bundle) {
    return $this->overrides[$bundle] ?? $this->original->get($entity_type_id, $bundle);
  }
  public function getByTypeName($type_name) { return $this->original->getByTypeName($type_name); }
};
$resource_fields = $resources->get('node', 'ai_session')->getFields();
foreach (['alias', 'disabled', 'internal'] as $case) {
  $changed = $resource_fields;
  if ($case === 'alias') {
    $changed['content'] = $changed['content']->withPublicName('renamed_content');
  }
  if ($case === 'disabled') {
    $changed['content'] = $changed['content']->disabled();
  }
  $repository->overrides['ai_session'] = new ResourceType('node', 'ai_session', Node::class,
    $case === 'internal', TRUE, TRUE, TRUE, $changed);
  $report = (new ChatSessionFieldScan($fields, \Drupal::entityTypeManager(), $repository))->scan();
  check($difference($report, $case === 'internal' ? 'resource_disabled' : 'definition_mismatch',
    $case === 'internal' ? NULL : 'content'), 'JSON:API preflight rejects ' . $case);
}
foreach (['required', 'default'] as $case) {
  $field_config = FieldConfig::loadByName('node', 'ai_session', 'storage_mode');
  $original = $field_config->toArray();
  match ($case) {
    'required' => $field_config->setRequired(TRUE),
    'default' => $field_config->setDefaultValue('private-default-do-not-output'),
  };
  $field_config->save();
  $report = $scan();
  check($difference($report, 'definition_mismatch', 'storage_mode'), 'Target field rejects ' . $case);
  check(!str_contains(json_encode($report), 'private-default-do-not-output'), 'Defaults are reported only as hashes');
  FieldConfig::load($field_config->id())->set($case === 'default' ? 'default_value' : $case,
    $original[$case === 'default' ? 'default_value' : $case])->save();
}
$field_config = FieldStorageConfig::loadByName('node', 'storage_mode');
$field_config->setTranslatable(TRUE)->save();
check($difference($scan(), 'target_storage_conflict', 'storage_mode'), 'Translatable target storage is rejected');
$field_config->setTranslatable(FALSE)->save();
// A data-bearing field cannot change SQL type; build the bad definition afresh in this fixture.
FieldStorageConfig::loadByName('node', 'edit_version')->delete();
field_purge_batch(1000);
field('ai_session', 'edit_version', 'integer', ['size' => 'normal']);
check($difference($scan(), 'target_storage_conflict', 'edit_version'), 'A 32-bit target counter is rejected');
FieldStorageConfig::loadByName('node', 'edit_version')->delete();
field_purge_batch(1000);
ChatSessionFields::install();
$field_config = FieldStorageConfig::loadByName('node', 'knowledge_refs');
$field_config->setCardinality(1)->save();
check($difference($scan(), 'target_storage_conflict', 'knowledge_refs'), 'Target cardinality drift is rejected');
$field_config->setCardinality(1024)->save();
$type = NodeType::load('conversation');
$type->setNewRevision(FALSE);
$type->save();
check($difference($scan(), 'revision_policy', NULL), 'Disabled revision creation is rejected');
$type->setNewRevision(TRUE);
$type->save();
$field_config = FieldConfig::loadByName('node', 'conversation', 'sessions');
$field_config->setSetting('handler_settings', ['target_bundles' => ['conversation' => 'conversation']])->save();
$drift = $scan();
check($difference($drift, 'definition_mismatch', 'sessions') &&
  $drift['sourceFingerprint'] !== $baseline['sourceFingerprint'],
  'Wrong relationship target bundle is rejected and changes the source fingerprint');
$field_config->setSetting('handler_settings', ['target_bundles' => ['ai_session' => 'ai_session']])->save();
field('ai_session', 'unexpected_copy', 'string_long');
check($difference($scan(), 'unexpected_field', 'unexpected_copy'), 'Unexpected custom fields are rejected');
FieldStorageConfig::loadByName('node', 'unexpected_copy')->delete();
field_purge_batch(1000);
FieldConfig::create(['entity_type' => 'node', 'bundle' => 'conflicting_type',
  'field_name' => 'run_id'])->save();
check($difference($scan(), 'target_storage_conflict', 'run_id'), 'Shared target storage is rejected');
FieldConfig::loadByName('node', 'conflicting_type', 'run_id')->delete();
field_purge_batch(1000);
FieldStorageConfig::loadByName('node', 'chat_type')->delete();
field_purge_batch(1000);
check($difference($scan(), 'field_missing', 'chat_type'), 'Missing source fields are rejected');
field('conversation', 'chat_type', 'integer');
check($difference($scan(), 'definition_mismatch', 'chat_type'), 'Wrong source field types are rejected');
FieldStorageConfig::loadByName('node', 'chat_type')->delete();
field_purge_batch(1000);
field('conversation', 'chat_type', 'string', ['max_length' => 255]);
FieldConfig::loadByName('node', 'conversation', 'chat_type')->setTranslatable(FALSE)->save();
FieldStorageConfig::loadByName('node', 'output_completion')->delete();
field_purge_batch(1000);
$partial = $scan();
check($partial['matches'] && !$partial['targets']['output_completion']['storage_exists'],
  'A missing target is reported separately and may be installed later');
check($partial['sourceFingerprint'] === $baseline['sourceFingerprint'] &&
  $partial['targetFingerprint'] !== $baseline['targetFingerprint'], 'Target changes leave the source fingerprint stable');
ChatSessionFields::install();
check($scan()['matches'], 'The common schema matches again after fixture restoration');
$invoke = static function (array $command): array {
  $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
  fclose($pipes[0]);
  $out = stream_get_contents($pipes[1]);
  $err = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  return [proc_close($process), $out, $err];
};
$command = [PHP_BINARY, '-d', 'extension=pdo_sqlite', '/app/vendor/drush/drush/drush.php',
  '--root=/app/docroot', '--uri=http://127.0.0.1:8080', 'php:script',
  '/app/scripts/scan-chat-session-fields.php'];
$before = $state_hash();
[$status, $out, $err] = $invoke($command);
$report = json_decode($out, TRUE);
check($status === 0 && $report && $report['matches'] && $report['operation'] === 'configuration-only',
  'Actual Drush invocation returns a matching configuration-only report: ' . $err);
check($state_hash() === $before, 'Drush field scan preserves configuration and content');
$type->setNewRevision(FALSE);
$type->save();
[$status, $out] = $invoke($command);
$report = json_decode($out, TRUE);
check($status === 1 && $report && $difference($report, 'revision_policy', NULL),
  'Actual Drush invocation exits with differences when a revision policy changes');
$type->setNewRevision(TRUE);
$type->save();
$process = proc_open([PHP_BINARY, '-r', "require '/app/scripts/scan-chat-session-fields.php';"],
  [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fclose($pipes[0]);
$out = stream_get_contents($pipes[1]);
$err = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
check(proc_close($process) === 2 && $out === '' && str_contains($err, 'selected site'),
  'Standalone scanner requires an explicitly bootstrapped Drush site');
echo 'Field scan checks: ' . ($GLOBALS['checks'] - $initial_checks) . PHP_EOL;
