<?php

declare(strict_types=1);

/**
 * @file
 * Included only by the disposable Drupal integration runner.
 */

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

$beforeChecks = $GLOBALS['checks'];
$counts = \Drupal::service('xinshi_analytics.counts');
$evidence = \Drupal::service('xinshi_analytics.evidence');
$role = Role::create(['id' => 'scoped_reader', 'label' => 'Scoped reader',
  'permissions' => ['access content', 'query analytics datasets', 'view own unpublished content']]);
$role->save();
$reader = User::create(['name' => 'scoped-reader', 'status' => 1, 'roles' => [$role->id()]]);
$reader->save();
asAccount($reader);
check($counts->discover('en') === ['datasets' => []], 'Automatic discovery is off without project configuration');
$settings = \Drupal::configFactory()->getEditable('xinshi_analytics.node_counts');
$policy = ['timezone' => 'UTC', 'languages' => ['en', 'fr'], 'max_calendar_months' => 12,
  'max_scanned_entities' => 500, 'max_groups' => 1, 'max_seconds' => 10];
$settings->set('enabled', TRUE)->set('query_policy', $policy)->save();
check(count(\Drupal::service('config.typed')->createFromNameAndData($settings->getName(),
  $settings->getRawData())->validate()) === 0, 'Automatic source settings have typed configuration schema');
$type = NodeType::create(['type' => 'scoped_entry', 'name' => 'Scoped entry']);
$type->save();
$empty = NodeType::create(['type' => 'scoped_empty', 'name' => 'Empty entry']);
$empty->save();
$storedBefore = count(\Drupal::entityTypeManager()->getStorage('analytics_dataset')->loadMultiple());
$find = function (string $id = 'node__scoped_entry') use ($counts): array {
  foreach ($counts->discover('en')['datasets'] as $dataset) {
    if ($dataset['id'] === $id) {
      return $dataset;
    }
  }
  throw new RuntimeException('Missing automatic type');
};
$descriptor = $find();
check($descriptor['scopes'] === ['all', 'range'] && $descriptor['timeBasis'] === 'created',
  'A newly created type is discovered with explicit creation-time semantics');
check(count(\Drupal::entityTypeManager()->getStorage('analytics_dataset')->loadMultiple()) === $storedBefore,
  'Discovery does not persist generated dataset definitions');
check(!in_array('editorial_activity', array_column($counts->discover('en')['datasets'], 'id'), TRUE),
  'General permission does not grant configured datasets');
$make = fn(array $dataset): array => ['datasetId' => $dataset['id'], 'datasetVersion' => $dataset['version'],
  'dimensions' => [], 'filters' => [], 'scope' => ['kind' => 'all'], 'timezone' => 'UTC', 'language' => 'en'];
foreach (['2026_entries', '_internal_entries'] as $machineName) {
  NodeType::create(['type' => $machineName, 'name' => 'Same label'])->save();
  $machineQuery = $make($find('node__' . $machineName));
  check($counts->count($machineQuery)['rows'][0]['count'] === 0,
    'Drupal machine names remain addressable independently of duplicate labels');
}
$all = $make($descriptor);
$range = $all;
$range['scope'] = ['kind' => 'range', 'from' => '2026-01-01T00:00:00Z', 'toExclusive' => '2026-02-01T00:00:00Z'];
$first = node('scoped_entry', $reader->id(), ['created' => strtotime('2026-01-01 UTC')]);
$first->addTranslation('fr', ['title' => 'French', 'status' => 1])->save();
node('scoped_entry', $reader->id(), ['status' => 0]);
node('scoped_entry', $reader->id(), ['created' => strtotime('2026-02-01 UTC')]);
node('scoped_entry', $reader->id(), ['created' => strtotime('2025-12-31 23:59:59 UTC')]);
node('scoped_entry', $other->id());
node_access_rebuild();
asAccount($reader);
check($counts->count($all)['rows'] === [['dimensions' => [], 'count' => 4]],
  'All counts every visible entity, including authorized unpublished content');
check($counts->count($range)['rows'] === [['dimensions' => [], 'count' => 2]],
  'Creation range includes its start, excludes its end and respects content grants');
check($counts->count($make($find('node__scoped_empty')))['rows'][0]['count'] === 0,
  'An existing empty type is a complete zero, not an unavailable dataset');
check($counts->count(array_replace($all, ['language' => 'fr']))['rows'][0]['count'] === 1,
  'Automatic counts retain exact translation selection');
foreach ([array_diff_key($all, ['scope' => TRUE]), $all + ['range' => $range['scope']],
  array_replace($all, ['scope' => ['kind' => 'all', 'from' => '2026-01-01T00:00:00Z']]),
  array_replace($all, ['scope' => ['kind' => 'range']]),
  array_replace($range, ['scope' => ['kind' => 'range', 'from' => '2026-02-30T00:00:00Z', 'toExclusive' => '2026-03-01T00:00:00Z']])
] as $invalid) {
  denied(fn() => $counts->count($invalid), 'invalid_query', 'Missing, mixed or invalid scoped intent is rejected');
}
$legacy = $all;
unset($legacy['scope']);
$legacy['range'] = array_diff_key($range['scope'], ['kind' => TRUE]);
denied(fn() => $counts->count($legacy), 'invalid_query', 'The source does not infer scope from a legacy range');
denied(fn() => $counts->count(array_replace($all, ['datasetId' => 'node__missing'])),
  'dataset_unavailable', 'Unknown types never become successful zeros');
denied(fn() => $counts->count(array_replace($all, ['datasetId' => 'editorial_activity'])),
  'dataset_unavailable', 'Configured datasets retain their independent grant');
$role->grantPermission('query analytics dataset editorial_activity')->save();
asAccount($reader);
$found = $counts->discover('en')['datasets'];
check(in_array('editorial_activity', array_column($found, 'id'), TRUE)
  && in_array('node__scoped_entry', array_column($found, 'id'), TRUE),
  'One discovery returns authorized configured and automatic datasets');
$configured = array_values(array_filter($found, fn($item) => $item['id'] === 'editorial_activity'))[0];
check($configured['timeBasis'] === 'configured' && $configured['scopes'] === ['all', 'range'],
  'Configured source exposes public time semantics under the same scope contract');
node('article', $reader->id());
node('article', $reader->id(), ['status' => 0]);
node_access_rebuild();
asAccount($reader);
$configuredAll = $counts->count($make($configured));
check($configuredAll['rows'] === [['dimensions' => [], 'count' => 1]],
  'Configured all scope excludes unpublished content even when the account can view it');
$role->revokePermission('query analytics dataset editorial_activity')->save();
asAccount($reader);
$collision = \Drupal\xinshi_analytics\Entity\AnalyticsDataset::create(
  definition('node__scoped_entry', 'node', 'scoped_entry', 'created', 'type'));
denied(fn() => $collision->save(), 'invalid_query', 'Configured IDs cannot use the automatic namespace');
$conflictingConfig = \Drupal::configFactory()->getEditable('xinshi_analytics.dataset.node__scoped_entry');
$conflictingConfig->setData($collision->toArray())->save();
check(!in_array('node__scoped_entry', array_column($counts->discover('en')['datasets'], 'id'), TRUE),
  'An existing namespace collision hides both definitions');
denied(fn() => $counts->count($all), 'dataset_unavailable', 'A collision never falls back to automatic grants');
$conflictingConfig->delete();
$wide = $range;
$wide['scope']['from'] = '2020-01-01T00:00:00Z';
denied(fn() => $counts->count($wide), 'range_too_large', 'Range month limits remain enforced');
$sealed = $evidence->capture($all);
check($sealed['version'] === 2 && $evidence->verify($sealed) === ['valid' => TRUE],
  'Canonical evidence binds a complete all-scope result');
denied(fn() => $evidence->verify(array_replace($sealed, ['version' => 1])), 'invalid_query',
  'Obsolete evidence envelopes are not migrated');
$tampered = $sealed;
$tampered['result']['query']['scope'] = $range['scope'];
denied(fn() => $evidence->verify($tampered), 'evidence_unavailable', 'Changing all to range cannot reuse a receipt');
file_put_contents('/tmp/analytics-test/scoped-protocol.json', json_encode([
  'discovery' => ['datasets' => [$descriptor]], 'all' => $counts->count($all),
  'range' => $counts->count($range), 'evidence' => $sealed, 'verification' => $evidence->verify($sealed),
], JSON_THROW_ON_ERROR));
$type->set('name', 'Renamed entry')->save();
check($find()['version'] !== $all['datasetVersion'] && $find()['label'] === 'Renamed entry',
  'A type rename changes the discovered version and label');
denied(fn() => $counts->count($all), 'dataset_version_changed', 'Saved queries cannot silently adopt a renamed type');
$type->set('name', 'Scoped entry')->save();
denied(fn() => $evidence->verify($sealed), 'evidence_unavailable', 'Restoring type configuration does not revive evidence');
$settings->set('query_policy.max_scanned_entities', 1)->save();
$limited = $make($find());
denied(fn() => $counts->count($limited), 'range_too_large', 'All scope still rejects scan-limit overflow');
$settings->set('query_policy', $policy)->save();
$all = $make($find());
$sealed = $evidence->capture($all);
\Drupal::state()->set('analytics_test.denied_field', 'created');
asAccount($reader);
check($counts->discover('en')['datasets'] === [], 'Definition-level field denial hides automatic datasets');
denied(fn() => $counts->count($all), 'dataset_unavailable', 'A generic grant does not bypass field access');
\Drupal::state()->delete('analytics_test.denied_field');
\Drupal::state()->set('analytics_test.denied_entity', ['node', $first->id()]);
asAccount($reader);
check($counts->count($all)['rows'][0]['count'] === 3, 'Per-entity access is checked after ID selection');
denied(fn() => $evidence->verify($sealed), 'evidence_unavailable', 'Access changes invalidate old automatic evidence');
\Drupal::state()->delete('analytics_test.denied_entity');
$authenticated = Role::load('authenticated');
$hadContentAccess = $authenticated->hasPermission('access content');
$authenticated->revokePermission('access content')->save();
$role->revokePermission('access content')->save();
asAccount($reader);
check($counts->discover('en')['datasets'] === [], 'General query permission alone does not reveal type metadata');
if ($hadContentAccess) {
  $authenticated->grantPermission('access content')->save();
}
$role->grantPermission('access content')->revokePermission('query analytics datasets')->save();
asAccount($reader);
denied(fn() => $counts->count($all), 'dataset_unavailable', 'General permission is refreshed on every query');
$role->grantPermission('query analytics datasets')->save();
asAccount($reader);
$emptyQuery = $make($find('node__scoped_empty'));
$empty->delete();
denied(fn() => $counts->count($emptyQuery), 'dataset_unavailable', 'Deleted types disappear without manual dataset cleanup');
NodeType::create(['type' => 'scoped_empty', 'name' => 'Empty entry'])->save();
denied(fn() => $counts->count($emptyQuery), 'dataset_version_changed', 'Recreating a type alias changes its identity version');
$settings->set('enabled', FALSE)->save();
check($counts->discover('en')['datasets'] === [], 'Project disablement closes automatic discovery');
denied(fn() => $counts->count($all), 'dataset_unavailable', 'Project disablement closes automatic counts');
asAccount(new AnonymousUserSession());
denied(fn() => $counts->discover('en'), 'dataset_unavailable', 'Anonymous discovery is denied');
$settings->delete();
echo 'PASS: ' . ($GLOBALS['checks'] - $beforeChecks) . ' scoped integration checks' . PHP_EOL;
