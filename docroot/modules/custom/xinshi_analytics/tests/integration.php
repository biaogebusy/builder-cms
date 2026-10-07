<?php

declare(strict_types=1);

/**
 * @file
 * Exercises actual Drupal configuration, storage, grants and field access.
 *
 * Requires a disposable network-none container with no mounts, a source copy
 * at /app and XINSHI_ANALYTICS_ISOLATED_TEST=1. Never uses an existing site.
 */

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_analytics\AnalyticsException;
use Drupal\xinshi_analytics\CountQuery;
use Drupal\xinshi_analytics\Entity\AnalyticsDataset;

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_ANALYTICS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv')
  || $root !== '/app' || file_exists('/tmp/analytics-test')
  || file_exists($root . '/docroot/sites/default/settings.php')) {
  throw new RuntimeException('Use a fresh disposable container with a source copy at /app.');
}
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
mkdir('/tmp/analytics-test');
file_put_contents('sites/default/settings.php', <<<'SETTINGS'
<?php
$databases['default']['default'] = [
  'driver' => 'sqlite', 'database' => '/tmp/analytics-test/site.sqlite',
  'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
];
$settings['hash_salt'] = 'disposable-analytics-tests';
$settings['config_sync_directory'] = '/tmp/analytics-test/config';
$settings['skip_permissions_hardening'] = TRUE;
$settings['extension_discovery_scan_tests'] = TRUE;
SETTINGS);
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += ['HTTP_HOST' => 'analytics-test', 'SERVER_NAME' => 'analytics-test',
  'SERVER_PORT' => 80, 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php',
  'SCRIPT_FILENAME' => $root . '/docroot/index.php', 'REQUEST_METHOD' => 'GET',
  'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'analytics-tests'];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'], 'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => [
    'site_name' => 'Analytics tests', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'fixture-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'test-only-password', 'pass2' => 'test-only-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE,
  ]],
]);
\Drupal::service('module_installer')->install(['xinshi_analytics']);
$checks = 0;
function check(bool $ok, string $message): void {
  if (!$ok) {
    throw new RuntimeException($message);
  }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function denied(callable $run, string $code, string $message): void {
  try {
    $run();
  }
  catch (Throwable $error) {
    do {
      if ($error->getMessage() === $code) {
        check(TRUE, $message);
        return;
      }
    } while ($error = $error->getPrevious());
    throw new RuntimeException('Wrong failure: ' . $message);
  }
  throw new RuntimeException('Expected failure: ' . $message);
}
function asAccount(User|AnonymousUserSession $account): void {
  \Drupal::currentUser()->setAccount($account);
  foreach (['node', 'taxonomy_term'] as $type) {
    if (\Drupal::entityTypeManager()->hasDefinition($type)) {
      \Drupal::entityTypeManager()->getAccessControlHandler($type)->resetCache();
    }
  }
}
function field(string $entityType, string $bundle, string $name, string $type, int $cardinality = 1): void {
  if (!FieldStorageConfig::loadByName($entityType, $name)) {
    FieldStorageConfig::create(['entity_type' => $entityType, 'field_name' => $name,
      'type' => $type, 'cardinality' => $cardinality])->save();
  }
  FieldConfig::create(['entity_type' => $entityType, 'bundle' => $bundle, 'field_name' => $name])->save();
}
function definition(string $id = 'editorial_activity', string $entityType = 'node', string $bundle = 'article',
  string $time = 'created', string $category = 'type'): array {
  return ['id' => $id, 'label' => $id, 'version' => 1, 'entity_type' => $entityType,
    'bundles' => [$bundle], 'time_field' => $time,
    'dimensions' => ['month' => ['label' => 'Month', 'field' => $time, 'kind' => 'month'],
      'category' => ['label' => 'Category', 'field' => $category, 'kind' => 'category']],
    'filters' => ['category' => ['label' => 'Category', 'field' => $category,
      'operators' => ['eq', 'in'], 'values' => ['article', 'open', 'closed']]],
    'query_policy' => ['timezone' => 'UTC', 'languages' => ['en', 'fr'],
      'max_calendar_months' => 12, 'max_scanned_entities' => 500, 'max_groups' => 100, 'max_seconds' => 10]];
}
function query(string $id = 'editorial_activity'): array {
  return ['datasetId' => $id, 'datasetVersion' => 1, 'dimensions' => ['category'], 'filters' => [],
    'scope' => ['kind' => 'range', 'from' => '2026-01-01T00:00:00Z', 'toExclusive' => '2026-02-01T00:00:00Z'],
    'timezone' => 'UTC', 'language' => 'en'];
}
function rows(array $query): array {
  return \Drupal::service('xinshi_analytics.counts')->count($query)['rows'];
}
function node(string $bundle, string $owner, array $values = []): Node {
  $node = Node::create($values + ['type' => $bundle, 'title' => 'Synthetic record',
    'uid' => $owner, 'status' => 1, 'langcode' => 'en', 'created' => strtotime('2026-01-10 UTC')]);
  $node->save();
  return $node;
}

check(!\Drupal::moduleHandler()->moduleExists('xinshi_ai'), 'Analytics installs without AI modules');
check(\Drupal::entityTypeManager()->getStorage('analytics_dataset')->loadMultiple() === [],
  'Installing the module does not publish any dataset');
\Drupal::service('module_installer')->install(['node', 'field', 'language', 'taxonomy', 'xinshi_analytics_test']);
ConfigurableLanguage::createFromLangcode('fr')->save();
foreach (['article', 'service_ticket'] as $bundle) {
  NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
}
field('node', 'service_ticket', 'field_opened_at', 'timestamp');
field('node', 'service_ticket', 'field_ticket_state', 'string');
field('node', 'service_ticket', 'field_multiple', 'string', -1);
Vocabulary::create(['vid' => 'activity', 'name' => 'Activity'])->save();
field('taxonomy_term', 'activity', 'field_occurred_at', 'timestamp');
field('taxonomy_term', 'activity', 'field_kind', 'string');
$editorial = AnalyticsDataset::create(definition());
$editorial->save();
$service = AnalyticsDataset::create(definition('service_activity', 'node', 'service_ticket',
  'field_opened_at', 'field_ticket_state'));
$service->save();
$terms = AnalyticsDataset::create(definition('term_activity', 'taxonomy_term', 'activity',
  'field_occurred_at', 'field_kind'));
$terms->save();
$role = Role::create(['id' => 'analytics_reader', 'label' => 'Analytics reader']);
foreach (['access content', 'query analytics datasets', $editorial->queryPermission(),
  $service->queryPermission(), $terms->queryPermission()] as $permission) {
  $role->grantPermission($permission);
}
$role->save();
$reader = User::create(['name' => 'reader', 'status' => 1, 'roles' => ['analytics_reader']]);
$reader->save();
$other = User::create(['name' => 'other', 'status' => 1, 'roles' => ['analytics_reader']]);
$other->save();
$counts = \Drupal::service('xinshi_analytics.counts');
asAccount($reader);
check(count($counts->discover('en')['datasets']) === 3, 'One service discovers unrelated configured sources');
$discovery = json_encode($counts->discover('en'));
check(!str_contains($discovery, 'field_ticket_state') && !str_contains($discovery, 'entity_type'),
  'Discovery excludes physical mappings');
$config = \Drupal::config('xinshi_analytics.dataset.service_activity')->getRawData();
check(count(\Drupal::service('config.typed')->createFromNameAndData('xinshi_analytics.dataset.service_activity', $config)->validate()) === 0,
  'Exported dataset passes the actual typed configuration schema');
check(in_array('node.type.service_ticket', $config['dependencies']['config'], TRUE)
  && in_array('field.field.node.service_ticket.field_ticket_state', $config['dependencies']['config'], TRUE)
  && in_array('field.storage.node.field_ticket_state', $config['dependencies']['config'], TRUE),
  'Dependencies include source bundle, field and field storage');
check(in_array('xinshi_analytics.dataset.editorial_activity', $role->getDependencies()['config'], TRUE),
  'Role dataset grants carry configuration dependencies');
check(!$editorial->access('update', $reader), 'Query permission does not grant configuration administration');

$one = node('article', $reader->id());
$two = node('article', $reader->id());
node('article', $other->id());
node('article', $reader->id(), ['status' => 0]);
node('article', $reader->id(), ['created' => strtotime('2026-02-01 UTC')]);
node('article', $reader->id(), ['created' => strtotime('2025-12-31 23:59:59 UTC')]);
$one->addTranslation('fr', ['title' => 'French record', 'status' => 1,
  'created' => strtotime('2026-01-10 UTC')])->save();
$two->addTranslation('fr', ['title' => 'Unpublished French record', 'status' => 0,
  'created' => strtotime('2026-01-10 UTC')])->save();
node_access_rebuild();
check(rows(query()) === [['dimensions' => ['article'], 'count' => 2]],
  'Node grants, published state and half-open range count each entity once');
file_put_contents('/tmp/analytics-test/protocol.json', json_encode([
  'discovery' => $counts->discover('en'), 'result' => $counts->count(query()),
], JSON_THROW_ON_ERROR));
asAccount($other);
check(rows(query()) === [['dimensions' => ['article'], 'count' => 1]],
  'Same permissions with another account produce another visible count');
asAccount($reader);
check(rows(array_replace(query(), ['language' => 'fr'])) === [['dimensions' => ['article'], 'count' => 1]],
  'Exact translated publication excludes unpublished and missing translations');
$one->setNewRevision(TRUE);
$one->isDefaultRevision(FALSE);
$one->setUnpublished();
$one->save();
check(rows(query()) === [['dimensions' => ['article'], 'count' => 2]],
  'Pending revision does not replace the current default revision');

foreach (['closed', 'open', NULL] as $state) {
  node('service_ticket', $reader->id(), ['field_opened_at' => strtotime('2026-01-10 UTC'),
    'field_ticket_state' => $state]);
}
node_access_rebuild();
$grouped = rows(query('service_activity'));
check(count($grouped) === 3 && array_sum(array_column($grouped, 'count')) === 3
  && in_array(['dimensions' => [NULL], 'count' => 1], $grouped, TRUE),
  'Different private fields yield category counts and a null bucket');
$withoutTime = node('service_ticket', $reader->id(), ['field_opened_at' => NULL]);
node_access_rebuild();
$allMonths = rows(array_replace(query('service_activity'), ['dimensions' => ['month'], 'scope' => ['kind' => 'all']]));
check(in_array(['dimensions' => [NULL], 'count' => 1], $allMonths, TRUE),
  'An empty configured timestamp produces a null month bucket in all scope');
$withoutTime->delete();
$filtered = array_replace(query('service_activity'), ['dimensions' => ['month', 'category'],
  'filters' => [['field' => 'category', 'operator' => 'eq', 'value' => 'closed']]]);
check(rows($filtered) === [['dimensions' => ['2026-01', 'closed'], 'count' => 1]],
  'Month and category grouping with an enum predicate');
$filtered['filters'] = [['field' => 'category', 'operator' => 'in', 'values' => ['closed', 'open']]];
check(array_sum(array_column(rows($filtered), 'count')) === 2, 'IN filters use exact enum values');
$empty = array_replace(query(), ['scope' => ['kind' => 'range', 'from' => '2024-01-01T00:00:00Z', 'toExclusive' => '2024-02-01T00:00:00Z']]);
check(rows($empty) === [], 'No grouped entities returns an empty table');
check(rows(array_replace($empty, ['dimensions' => []])) === [['dimensions' => [], 'count' => 0]],
  'No ungrouped entities returns one explicit zero');

$term = Term::create(['vid' => 'activity', 'name' => 'Visible term', 'status' => 1, 'langcode' => 'en',
  'field_occurred_at' => strtotime('2026-01-15 UTC'), 'field_kind' => 'open']);
$term->save();
check(rows(query('term_activity')) === [['dimensions' => ['open'], 'count' => 1]],
  'The same service counts a real non-node entity source');
\Drupal::state()->set('analytics_test.denied_entity', ['taxonomy_term', $term->id()]);
asAccount($reader);
check(rows(query('term_activity')) === [], 'Per-entity access denial applies after the entity query');
\Drupal::state()->delete('analytics_test.denied_entity');

// Permission checks run before a private predicate can influence the total.
\Drupal::state()->set('analytics_test.denied_field', 'field_ticket_state');
asAccount($reader);
denied(fn() => rows($filtered), 'dataset_unavailable', 'A globally hidden filter field rejects the dataset');
check(!in_array('service_activity', array_column($counts->discover('en')['datasets'], 'id'), TRUE),
  'Discovery also hides a dataset with denied fields');
\Drupal::state()->set('analytics_test.denied_field', 'field_kind');
\Drupal::state()->set('analytics_test.denied_field_entity', $term->id());
asAccount($reader);
check(rows(query('term_activity')) === [], 'Per-entity field access excludes that entity before reading its value');
\Drupal::state()->delete('analytics_test.denied_field');
\Drupal::state()->delete('analytics_test.denied_field_entity');
asAccount($reader);
check(rows(query('term_activity')) === [['dimensions' => ['open'], 'count' => 1]], 'Restored field access permits a fresh query');

\Drupal::state()->set('analytics_test.denied_field', 'field_opened_at');
asAccount($reader);
denied(fn() => rows(query('service_activity')), 'dataset_unavailable',
  'A hidden range field cannot influence a successful count');
\Drupal::state()->delete('analytics_test.denied_field');
asAccount($reader);

foreach ([
  ['metrics' => ['sum']], ['actor' => '1'], ['datasetVersion' => '1'], ['dimensions' => ['category', 'category']],
  ['dimensions' => ['field_secret.value']], ['dimensions' => ['unknown']], ['timezone' => '+01:00'],
  ['scope' => ['kind' => 'range', 'from' => '2026-02-30T00:00:00Z', 'toExclusive' => '2026-03-02T00:00:00Z']],
  ['filters' => [['field' => 'category', 'operator' => 'eq', 'value' => 'private']]],
  ['filters' => [['field' => 'category', 'operator' => 'eq', 'value' => 'article'],
    ['field' => 'category', 'operator' => 'eq', 'value' => 'article']]],
] as $index => $override) {
  denied(fn() => rows(array_replace(query(), $override)), 'invalid_query', 'Invalid or unsupported query rejected: ' . $index);
}
denied(fn() => rows(array_replace(query(), ['datasetVersion' => 2])), 'dataset_version_changed', 'Stale dataset version is explicit');
denied(fn() => rows(array_replace(query(), ['datasetId' => 'missing'])), 'dataset_unavailable', 'Unknown dataset reveals no mapping');
denied(fn() => rows(array_replace(query(), ['language' => 'de'])), 'dataset_unavailable', 'Unsupported language never falls back');
$range = ['kind' => 'range', 'from' => '2025-01-01T00:00:00Z', 'toExclusive' => '2026-02-01T00:00:00Z'];
denied(fn() => rows(array_replace(query(), ['scope' => $range])), 'range_too_large', 'Calendar month limit rejects excessive ranges');

$before = $service->get('query_policy');
foreach (['max_scanned_entities' => 2, 'max_groups' => 1] as $limit => $value) {
  $service->set('query_policy', array_replace($before, [$limit => $value]));
  $service->set('version', $service->get('version') + 1)->save();
  denied(fn() => rows(array_replace(query('service_activity'), ['datasetVersion' => $service->get('version')])),
    'range_too_large', 'Limit rejects partial count: ' . $limit);
  check(\Drupal::currentUser()->id() === $reader->id(), 'Failure restores the authenticated outer account');
}
$service->set('query_policy', $before)->set('version', $service->get('version') + 1)->save();

$editorial->set('label', 'Changed meaning');
denied(fn() => $editorial->save(), 'Dataset changes require the same ID and a newer version.',
  'Mapping metadata changes cannot silently reuse a version');
$editorial = AnalyticsDataset::load('editorial_activity');
$editorial->disable()->save();
denied(fn() => rows(query()), 'dataset_unavailable', 'Disabled datasets stop execution');
$editorial->enable()->save();

$role->revokePermission($editorial->queryPermission())->save();
asAccount(User::load($reader->id()));
denied(fn() => rows(query()), 'dataset_unavailable', 'Dataset permission revocation blocks the next query');
$role->grantPermission($editorial->queryPermission())->save();
$reader->block()->save();
asAccount($reader);
denied(fn() => rows(query()), 'dataset_unavailable', 'Blocked account cannot use a previously granted dataset');
$reader->activate()->save();
$noGrant = User::create(['name' => 'no-grant', 'status' => 1]);
$noGrant->save();
asAccount($noGrant);
denied(fn() => rows(query()), 'dataset_unavailable', 'Authenticated users need the general query grant');
asAccount(new AnonymousUserSession());
denied(fn() => rows(query()), 'dataset_unavailable', 'Anonymous users cannot query even public content');
asAccount($reader);

$bad = definition('bad_source');
$bad['entity_type'] = 'user';
denied(fn() => AnalyticsDataset::create($bad)->save(), 'dataset_unavailable', 'Unsupported entity types fail during configuration save');
$bad = definition('bad_field', 'node', 'service_ticket', 'field_opened_at', 'field_multiple');
denied(fn() => AnalyticsDataset::create($bad)->save(), 'dataset_unavailable', 'Multi-value dimensions cannot duplicate entity counts');
$bad = definition('missing_field');
$bad['time_field'] = 'field_missing';
$bad['dimensions']['month']['field'] = 'field_missing';
denied(fn() => AnalyticsDataset::create($bad)->save(), 'dataset_unavailable', 'Missing field mappings reject configuration');
$bad = definition('bad_language');
$bad['query_policy']['languages'] = ['de'];
denied(fn() => AnalyticsDataset::create($bad)->save(), 'dataset_unavailable',
  'Uninstalled languages cannot be advertised by a dataset');
$bad = definition('bad_limit');
$bad['query_policy']['max_groups'] = 101;
denied(fn() => AnalyticsDataset::create($bad)->save(), 'dataset_unavailable',
  'Configuration cannot exceed public protocol limits');
$bad = definition('time_as_category');
$bad['dimensions'] = ['category' => ['label' => 'Category', 'field' => 'created', 'kind' => 'category'],
  'month' => ['label' => 'Month', 'field' => 'created', 'kind' => 'month']];
denied(fn() => AnalyticsDataset::create($bad)->save(), 'dataset_unavailable',
  'Dimension ordering cannot repurpose the timestamp as a category');

// A configured local timezone controls both bucket labels and calendar limits.
$time = definition('local_calendar', 'node', 'service_ticket', 'field_opened_at', 'field_ticket_state');
$time['query_policy']['timezone'] = 'America/New_York';
$time['query_policy']['max_calendar_months'] = 1;
$calendar = AnalyticsDataset::create($time);
$calendar->save();
$role->grantPermission($calendar->queryPermission())->save();
node('service_ticket', $reader->id(), ['field_opened_at' => strtotime('2026-03-08T07:00:00Z'), 'field_ticket_state' => 'open']);
node_access_rebuild();
$local = array_replace(query('local_calendar'), ['dimensions' => ['month'], 'timezone' => 'America/New_York',
  'scope' => ['kind' => 'range', 'from' => '2026-03-01T05:00:00Z', 'toExclusive' => '2026-04-01T04:00:00Z']]);
check(rows($local) === [['dimensions' => ['2026-03'], 'count' => 1]], 'Named timezone handles a DST month using a half-open UTC range');
$local['scope']['from'] = '2026-03-01T04:59:59Z';
denied(fn() => rows($local), 'range_too_large', 'One extra second in the prior local month exceeds the calendar limit');

NodeType::create(['type' => 'batch_entry', 'name' => 'Batch entry'])->save();
$batchDefinition = definition('batch_activity', 'node', 'batch_entry');
$batchDefinition['query_policy']['max_scanned_entities'] = 101;
$batch = AnalyticsDataset::create($batchDefinition);
$batch->save();
$role->grantPermission($batch->queryPermission())->save();
for ($i = 0; $i < 101; $i++) {
  node('batch_entry', $reader->id());
}
node_access_rebuild();
check(rows(array_replace(query('batch_activity'), ['dimensions' => []])) === [['dimensions' => [], 'count' => 101]],
  'Keyset pagination counts the exact scan limit across multiple batches');
node('batch_entry', $reader->id(), ['created' => strtotime('2026-02-01 UTC')]);
node_access_rebuild();
denied(fn() => rows(query('batch_activity')), 'range_too_large',
  'One extra candidate rejects the query even when it is outside the time predicate');

// Dependency removal cannot leave an executable dataset pointing at a deleted field.
FieldConfig::loadByName('taxonomy_term', 'activity', 'field_kind')->delete();
check(AnalyticsDataset::load('term_activity') === NULL, 'Deleting a required field removes its dependent dataset');
denied(fn() => rows(query('term_activity')), 'dataset_unavailable', 'Removed mappings cannot be executed');
echo 'PASS: ' . $checks . ' real Drupal integration checks' . PHP_EOL;

require __DIR__ . '/evidence.integration.php';

require __DIR__ . '/scoped.integration.php';
