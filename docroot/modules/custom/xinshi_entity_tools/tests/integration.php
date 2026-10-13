<?php

declare(strict_types=1);

/**
 * @file
 * Actual Drupal schema, grants, field validation, revisions and atomic receipts.
 */

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;
use Drupal\xinshi_entity_tools\Entity\EntityToolResource;

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_ENTITY_TOOLS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  $root !== '/app' || file_exists('/tmp/entity-tools-test') || file_exists($root . '/docroot/sites/default/settings.php')) {
  throw new RuntimeException('Use a fresh disposable container with a source copy at /app.');
}
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
mkdir('/tmp/entity-tools-test');
file_put_contents('sites/default/settings.php', <<<'SETTINGS'
<?php
$databases['default']['default'] = [
  'driver' => 'sqlite', 'database' => '/tmp/entity-tools-test/site.sqlite',
  'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
];
$settings['hash_salt'] = 'disposable-entity-tool-tests';
$settings['config_sync_directory'] = '/tmp/entity-tools-test/config';
$settings['skip_permissions_hardening'] = TRUE;
$settings['extension_discovery_scan_tests'] = TRUE;
SETTINGS);
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += ['HTTP_HOST' => 'entity-tools-test', 'SERVER_NAME' => 'entity-tools-test',
  'SERVER_PORT' => 80, 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php',
  'SCRIPT_FILENAME' => $root . '/docroot/index.php', 'REQUEST_METHOD' => 'GET',
  'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'entity-tool-tests'];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'], 'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => [
    'site_name' => 'Entity tool tests', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'fixture-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'test-only-password', 'pass2' => 'test-only-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE,
  ]],
]);
\Drupal::service('module_installer')->install(['xinshi_entity_tools', 'field', 'options',
  'language', 'content_translation', 'xinshi_entity_tools_test']);
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
    $original = $error;
    do {
      if ($error->getMessage() === $code) {
        check(TRUE, $message);
        return;
      }
    } while ($error = $error->getPrevious());
    throw new RuntimeException('Wrong failure: ' . $message . ': ' . $original->getMessage(), 0, $original);
  }
  throw new RuntimeException('Expected failure: ' . $message);
}
function asAccount(User|AnonymousUserSession $account): void {
  \Drupal::currentUser()->setAccount($account);
  \Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();
}
function field(string $bundle, string $name, string $type, array $settings = [], bool $required = FALSE, int $cardinality = 1): void {
  FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => $name, 'type' => $type,
    'settings' => $settings, 'cardinality' => $cardinality])->save();
  FieldConfig::create(['entity_type' => 'node', 'bundle' => $bundle, 'field_name' => $name,
    'required' => $required, 'translatable' => TRUE])->save();
}
function input(string $resource = 'articles', array $values = ['title' => 'Synthetic article']): stdClass {
  return (object) ['operation' => 'create', 'resourceId' => $resource, 'resourceVersion' => 1,
    'language' => 'en', 'values' => (object) $values];
}
function readInput(array $result, array $fields = ['title']): stdClass {
  return (object) ['resourceId' => 'articles', 'resourceVersion' => 1,
    'language' => 'en', 'entityId' => $result['id'], 'fields' => $fields];
}
function operation(): string {
  return \Drupal::service('uuid')->generate();
}
function countRows(string $table): int {
  return (int) \Drupal::database()->select($table, 't')->countQuery()->execute()->fetchField();
}

$catalog = \Drupal::service('xinshi_entity_tools.resources');
$entities = \Drupal::service('xinshi_entity_tools.entities');
check(!\Drupal::moduleHandler()->moduleExists('xinshi_ai'), 'Optional entity source installs without AI modules');
check(EntityToolResource::loadMultiple() === [], 'Installation does not publish default resources');
ConfigurableLanguage::createFromLangcode('fr')->save();
foreach (['article', 'product'] as $bundle) {
  NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
}
field('article', 'body', 'text_with_summary');
field('article', 'field_stage', 'list_string', ['allowed_values' => ['draft' => 'Draft', 'ready' => 'Ready']]);
field('product', 'field_sku', 'string', ['max_length' => 12], TRUE);
field('product', 'field_stock', 'integer', [], TRUE);
field('product', 'field_active', 'boolean');
field('article', 'field_multiple', 'string', [], FALSE, -1);
field('article', 'field_reference', 'entity_reference', ['target_type' => 'node']);
$article = EntityToolResource::create(['id' => 'articles', 'label' => 'Articles', 'version' => 1,
  'bundle' => 'article', 'fields' => ['title', 'body', 'field_stage'],
  'write_fields' => ['title', 'body', 'field_stage'], 'languages' => ['en', 'fr'],
  'text_format' => 'plain_text', 'allow_create' => TRUE, 'allow_update' => TRUE]);
$article->save();
$product = EntityToolResource::create(['id' => 'products', 'label' => 'Products', 'version' => 1,
  'bundle' => 'product', 'fields' => ['title', 'field_sku', 'field_stock', 'field_active'],
  'write_fields' => ['title', 'field_sku', 'field_stock', 'field_active'], 'languages' => ['en'],
  'text_format' => 'plain_text', 'allow_create' => TRUE, 'allow_update' => TRUE]);
$product->save();
$config = \Drupal::config('xinshi_entity_tools.resource.articles')->getRawData();
check(count(\Drupal::service('config.typed')->createFromNameAndData('xinshi_entity_tools.resource.articles', $config)->validate()) === 0,
  'Resource exports pass the actual typed configuration schema');
check(in_array('field.field.node.article.body', $config['dependencies']['config'], TRUE),
  'Resources depend on actual field configuration');
foreach (['field_multiple', 'field_reference', 'uid'] as $unsupported) {
  $invalid = clone $article;
  $invalid->set('fields', ['title', $unsupported]);
  $invalid->set('write_fields', []);
  $invalid->set('allow_create', FALSE);
  denied(fn() => $catalog->validate($invalid), 'Unsupported field: ' . $unsupported,
    'Unsupported field cannot be exposed: ' . $unsupported);
}
$role = Role::create(['id' => 'entity_editor', 'label' => 'Entity editor']);
foreach (['access content', 'view own unpublished content', 'use entity tools', 'write entity tools',
  'create article content', 'edit own article content', 'create product content', 'edit own product content'] as $permission) {
  $role->grantPermission($permission);
}
$role->save();
$owner = User::create(['name' => 'entity-owner', 'status' => 1, 'roles' => ['entity_editor']]);
$owner->save();
$other = User::create(['name' => 'entity-other', 'status' => 1, 'roles' => ['entity_editor']]);
$other->save();
asAccount(new AnonymousUserSession());
denied(fn() => $catalog->discover(), 'permission_denied', 'Anonymous discovery is denied');
asAccount($owner);
check($catalog->capabilities()['permissions'] === ['entities.read', 'entities.write'], 'Capabilities come from effective grants');
check(count($catalog->discover()['resources']) === 2, 'One catalog discovers two different content structures');
check($catalog->describe('products')['fields'][2]['type'] === 'integer', 'Discovery uses actual Typed Data field types');
check(!$article->access('update', $owner), 'Content tool permission does not grant resource administration');
$before = countRows('node');
$articleInput = input('articles', ['title' => 'Synthetic 中文 article',
  'body' => (object) ['value' => 'Evidence body', 'summary' => 'Short'], 'field_stage' => 'draft']);
check($entities->validate($articleInput)['valid'], 'Valid article fields pass actual entity validation');
check(countRows('node') === $before && countRows('xinshi_entity_tool_operation') === 0,
  'Validation saves neither entities nor receipts');
$productInvalid = input('products', ['title' => 'Product']);
check(!$entities->validate($productInvalid)['valid'], 'Different resource required fields are enforced');
denied(fn() => $entities->validate(input('products', ['title' => 'Product', 'field_sku' => 'SKU', 'field_stock' => '3'])),
  'invalid_input', 'Numeric strings are never coerced to field integers');
check(!$entities->validate(input('articles', ['title' => 'Bad enum', 'field_stage' => 'invented']))['valid'],
  'Drupal allowed-values constraints reject invented enums');
$longTitle = $entities->validate(input('articles', ['title' => str_repeat('Long title ', 50)]));
check(!$longTitle['valid'] && mb_strlen($longTitle['target']['label']) === 255,
  'Overlong titles return field errors within the response schema');
check(!$entities->validate(input('articles', ['title' => str_repeat('文', 20000),
  'body' => (object) ['value' => str_repeat('文', 20000)], 'field_stage' => str_repeat('文', 20000)]))['valid'],
  'UTF-8-sized inputs reach field validation without JSON escape expansion');
$execution = operation();
$created = $entities->write($execution, $articleInput);
check($created['result']['status'] === 'draft' && countRows('node') === $before + 1, 'Creation persists exactly one unpublished node');
check($entities->write($execution, $articleInput) == $created && countRows('node') === $before + 1,
  'Duplicate execution reuses its atomic receipt');
denied(fn() => $entities->write($execution, input()), 'version_conflict', 'Execution IDs cannot be reused for different approved values');
$productCreated = $entities->write(operation(), input('products', ['title' => 'Synthetic product',
  'field_sku' => 'SKU-42', 'field_stock' => 3, 'field_active' => TRUE]));
check($productCreated['result']['status'] === 'draft', 'The same write service saves the second business structure');
$snapshot = $entities->read(readInput($created['result'], ['title', 'body']));
check($snapshot['entity']['values']->body['value'] === 'Evidence body', 'Read returns explicit fields of the exact entity');
$update = (object) ['operation' => 'update', 'resourceId' => 'articles', 'resourceVersion' => 1,
  'language' => 'en', 'entityId' => $created['result']['id'],
  'expectedVersion' => $snapshot['entity']['version'], 'values' => (object) ['title' => 'Revised article']];
$updated = $entities->write(operation(), $update);
check($updated['result']['revisionId'] !== $created['result']['revisionId'], 'Updating creates a new explicit revision');
$oldRevision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($created['result']['revisionId']);
check($oldRevision->label() === 'Synthetic 中文 article', 'Previous revision content remains unchanged');
check($entities->read(readInput($updated['result'], ['body']))['entity']['values']->body['value'] === 'Evidence body',
  'Patches preserve fields omitted from the approved input');
denied(fn() => $entities->write(operation(), $update), 'version_conflict', 'Stale approved version is refused');
asAccount($other);
denied(fn() => $entities->read(readInput($updated['result'])), 'entity_unavailable', 'Other authors cannot read an unauthorized draft');
denied(fn() => $entities->find($execution), 'permission_denied', 'Receipts remain bound to the original account');
asAccount($owner);
$query = (object) ['resourceId' => 'articles', 'resourceVersion' => 1, 'language' => 'en',
  'fields' => ['title'], 'filters' => [(object) ['field' => 'title', 'operator' => 'contains', 'value' => 'REVISED']], 'limit' => 1];
check(count($entities->query($query)['items']) === 1, 'Queries apply authorized case-insensitive predicates');
$productQuery = (object) ['resourceId' => 'products', 'resourceVersion' => 1, 'language' => 'en',
  'fields' => ['title'], 'filters' => [], 'limit' => 1];
foreach ([['field_stock', 'contains', '3'], ['field_stock', 'eq', '3'],
  ['field_active', 'eq', 1], ['title', 'eq', TRUE]] as [$name, $operator, $value]) {
  $productQuery->filters = [(object) ['field' => $name, 'operator' => $operator, 'value' => $value]];
  denied(fn() => $entities->query($productQuery), 'invalid_input', 'Filter types match the actual field: ' . $name);
}
$productQuery->filters = [(object) ['field' => 'field_stock', 'operator' => 'eq', 'value' => 3]];
check(count($entities->query($productQuery)['items']) === 1, 'Typed integer predicates match without coercion');
foreach (['nid', 'uuid', 'vid', 'langcode', 'status'] as $name) {
  \Drupal::state()->set('entity_tools_test.denied_field', ['view', $name]);
  asAccount($owner);
  denied(fn() => $entities->read(readInput($updated['result'])), 'entity_unavailable', 'Metadata field access: ' . $name);
  check($entities->query($query)['items'] === [], 'Queries omit inaccessible metadata: ' . $name);
  denied(fn() => $entities->find($execution), 'entity_unavailable', 'Receipts respect metadata access: ' . $name);
}
\Drupal::state()->delete('entity_tools_test.denied_field');
\Drupal::state()->set('entity_tools_test.denied_field', ['view', 'body']);
asAccount($owner);
denied(fn() => $entities->read(readInput($updated['result'], ['body'])), 'field_denied', 'Field view denial is enforced on details');
$privateFilter = clone $query;
$privateFilter->filters = [(object) ['field' => 'body', 'operator' => 'contains', 'value' => 'Evidence']];
check($entities->query($privateFilter)['items'] === [], 'Private field predicates cannot reveal matching records');
denied(fn() => $entities->find($execution), 'field_denied', 'Receipt reads also respect withdrawn field access');
\Drupal::state()->delete('entity_tools_test.denied_field');
\Drupal::state()->set('entity_tools_test.denied_field', ['edit', 'title']);
asAccount($owner);
denied(fn() => $entities->validate(input()), 'field_denied', 'Field edit denial is enforced before approval');
\Drupal::state()->delete('entity_tools_test.denied_field');
asAccount($owner);
$node = Node::load($updated['result']['id']);
$node->set('title', 'External in-place edit')->save();
$stale = clone $update;
$stale->expectedVersion = $updated['result']['version'];
denied(fn() => $entities->write(operation(), $stale), 'version_conflict', 'In-place edits invalidate the content hash even without a new revision');
$node->setPublished()->save();
$published = $entities->read(readInput($updated['result']));
$stale->expectedVersion = $published['entity']['version'];
denied(fn() => $entities->write(operation(), $stale), 'draft_not_supported', 'Published entities cannot be changed by draft tools');
$node->setUnpublished()->save();
$node->addTranslation('fr', ['title' => 'Article français'])->save();
$translatedInput = readInput($updated['result']);
$translatedInput->language = 'fr';
check($entities->read($translatedInput)['entity']['values']->title === 'Article français', 'Reads use the exact requested translation');
$stale->expectedVersion = $entities->read(readInput($updated['result']))['entity']['version'];
denied(fn() => $entities->write(operation(), $stale), 'draft_not_supported', 'Translated entities cannot be mutated through the first draft contract');
$before = countRows('node');
$receipts = countRows('xinshi_entity_tool_operation');
\Drupal::state()->set('entity_tools_test.publish_on_save', TRUE);
denied(fn() => $entities->write(operation(), input()), 'draft_not_supported', 'Presave publication is rolled back');
\Drupal::state()->delete('entity_tools_test.publish_on_save');
check(countRows('node') === $before && countRows('xinshi_entity_tool_operation') === $receipts,
  'Publication rejection rolls back both content and reservation');
\Drupal::state()->set('entity_tools_test.fail_on_save', TRUE);
denied(fn() => $entities->write(operation(), input()), 'Synthetic save failure', 'Save exceptions preserve the original failure');
\Drupal::state()->delete('entity_tools_test.fail_on_save');
check(countRows('node') === $before && countRows('xinshi_entity_tool_operation') === $receipts,
  'Save failures leave no partial entity or receipt');
$article->set('version', 2)->set('label', 'Revised resource')->save();
denied(fn() => $entities->validate(input()), 'version_conflict', 'Resource changes invalidate old proposal versions');
$article->set('version', 3)->disable()->save();
$role->revokePermission('write entity tools')->save();
asAccount(User::load($owner->id()));
check($entities->find($execution)['result'] === $created['result'],
  'Original submitted writes reconcile after resource disablement and write revocation');
$article->set('version', 4)->enable()->save();
$role->grantPermission('write entity tools')->save();
$article->set('version', 5)->set('text_format', 'plain_text')->save();
FilterFormat::create(['format' => 'restricted_text', 'name' => 'Restricted', 'filters' => []])->save();
$article->set('version', 6)->set('text_format', 'restricted_text')->save();
$formatInput = input('articles', ['title' => 'Format test', 'body' => (object) ['value' => 'Text']]);
$formatInput->resourceVersion = 6;
asAccount(User::load($owner->id()));
denied(fn() => $entities->validate($formatInput), 'field_denied', 'Configured text format still requires current author permission');
$article->set('version', 7)->set('text_format', 'plain_text')->save();
$owner->block()->save();
asAccount($owner);
check($catalog->capabilities()['permissions'] === [], 'Blocked accounts receive no tool capability');
$owner->activate()->save();
asAccount($owner);
\Drupal::service('module_installer')->install(['content_moderation']);
$workflow = Workflow::create(['id' => 'entity_editorial', 'label' => 'Entity editorial',
  'type' => 'content_moderation', 'type_settings' => [
    'states' => [
      'draft' => ['label' => 'Draft', 'published' => FALSE, 'default_revision' => FALSE, 'weight' => 0],
      'published' => ['label' => 'Published', 'published' => TRUE, 'default_revision' => TRUE, 'weight' => 1],
    ],
    'transitions' => ['create_new_draft' => ['label' => 'Create draft', 'from' => ['draft', 'published'], 'to' => 'draft', 'weight' => 0]],
    'entity_types' => ['node' => ['product']], 'default_moderation_state' => 'draft',
  ]]);
$workflow->save();
$entities = \Drupal::service('xinshi_entity_tools.entities');
$moderatedInput = input('products', ['title' => 'Moderated product', 'field_sku' => 'MOD', 'field_stock' => 1]);
denied(fn() => $entities->validate($moderatedInput), 'draft_not_supported', 'Moderation transitions require effective permission');
$role->grantPermission('use entity_editorial transition create_new_draft')->save();
asAccount(User::load($owner->id()));
check($entities->write(operation(), $moderatedInput)['result']['status'] === 'draft',
  'Allowed moderation transition creates an unpublished draft');
file_put_contents('/tmp/entity-tools-test/fixture.json', json_encode(['owner' => $owner->id(),
  'other' => $other->id(), 'entity' => $updated['result']['id'], 'execution' => $execution], JSON_THROW_ON_ERROR));
echo 'Entity tool integration: ' . $checks . ' checks passed.' . PHP_EOL;
