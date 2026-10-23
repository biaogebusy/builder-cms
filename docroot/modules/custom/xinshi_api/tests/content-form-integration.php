<?php

declare(strict_types=1);

/**
 * @file
 * Installs a disposable SQLite site for authorized content form checks.
 *
 * Run only in an empty container with a source copy at /app, no site mounts,
 * and XINSHI_CONTENT_FORM_TEST=1. Never point this runner at a real site.
 */

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_CONTENT_FORM_TEST') !== '1' || !is_file('/.dockerenv') ||
    $root !== '/app' || file_exists($root . '/docroot/sites/default/settings.php') ||
    file_exists('/tmp/content-form-test')) {
  throw new RuntimeException('Use a fresh, isolated test container with a source copy at /app.');
}
require __DIR__ . '/bootstrap.php';
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
mkdir('/tmp/content-form-test');
file_put_contents('sites/default/settings.php', <<<'PHP'
<?php
$databases['default']['default'] = [
  'driver' => 'sqlite', 'database' => '/tmp/content-form-test/site.sqlite',
  'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
];
$settings['hash_salt'] = 'isolated-api-integration-tests-only';
$settings['config_sync_directory'] = '/tmp/content-form-test/config';
$settings['skip_permissions_hardening'] = TRUE;
PHP);
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += [
  'HTTP_HOST' => 'api-test', 'SERVER_NAME' => 'api-test', 'SERVER_PORT' => 80,
  'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => $root . '/docroot/index.php',
  'REQUEST_METHOD' => 'GET', 'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'api-tests',
];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'],
  'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => [
    'site_name' => 'API integration tests', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'test-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'test-only-password', 'pass2' => 'test-only-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE,
  ]],
]);
\Drupal::service('module_installer')->install(['xinshi_api', 'jsonapi', 'field_ui', 'text', 'options', 'comment', 'language', 'content_translation', 'jsonapi_extras']);

$checks = 0;
function check(bool $ok, string $message): void {
  if (!$ok) { throw new RuntimeException($message); }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function denied(callable $run, int $status, string $message): void {
  try { $run(); }
  catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
    check($error->getStatusCode() === $status, $message);
    return;
  }
  throw new RuntimeException('Expected denial: ' . $message);
}
function account($user): void {
  \Drupal::currentUser()->setAccount($user);
  \Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();
  \Drupal::entityTypeManager()->getAccessControlHandler('comment')->resetCache();
}
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\filter\Entity\FilterFormat;

NodeType::create(['type' => 'blog', 'name' => 'Blog'])->save();
NodeType::create(['type' => 'private_type', 'name' => 'Private'])->save();
FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => 'body', 'type' => 'text_with_summary'])->save();
FieldConfig::create(['entity_type' => 'node', 'bundle' => 'blog', 'field_name' => 'body', 'label' => 'Body', 'required' => TRUE])->save();
FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => 'field_hidden', 'type' => 'string'])->save();
FieldConfig::create(['entity_type' => 'node', 'bundle' => 'blog', 'field_name' => 'field_hidden'])->save();
EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => 'blog', 'mode' => 'default', 'status' => TRUE])
  ->setComponent('title', ['type' => 'string_textfield'])->setComponent('body', ['type' => 'text_textarea_with_summary'])
  ->removeComponent('field_hidden')->save();
$format = FilterFormat::create(['format' => 'basic_html', 'name' => 'Basic HTML', 'weight' => -10,
  'filters' => ['filter_html' => ['status' => TRUE, 'settings' => ['allowed_html' => '<p> <strong> <em>']]]]);
$format->save();
FilterFormat::create(['format' => 'full_html', 'name' => 'Full HTML', 'weight' => -20])->save();
$permissions = ['access content', 'create blog content', 'edit own blog content', 'view own unpublished content',
  'use text format basic_html', 'access comments', 'post comments', 'edit own comments'];
Role::create(['id' => 'writer', 'label' => 'Writer', 'permissions' => $permissions])->save();
Role::create(['id' => 'editor_only', 'label' => 'Editor only', 'permissions' => array_values(array_diff($permissions, ['create blog content']))])->save();
$writer = User::create(['name' => 'writer', 'status' => 1, 'roles' => ['writer']]); $writer->save();
$other = User::create(['name' => 'other', 'status' => 1, 'roles' => ['writer']]); $other->save();
$editor = User::create(['name' => 'editor', 'status' => 1, 'roles' => ['editor_only']]); $editor->save();
account($writer);
$service = \Drupal::service('xinshi_api.content_forms');
check(!$writer->hasPermission('administer node fields'), 'Writer has no field administration permission');
check(array_column($service->types(), 'value') === ['blog'], 'Type discovery checks actual create access');
$form = $service->node('blog', NULL, NULL);
check(array_column($form['fields'], 'key') === ['body'], 'Hidden fields are excluded without reading configuration entities as the user');
check($form['fields'][0]['textFormat'] === 'basic_html', 'Body chooses an allowed format rather than full_html');
check(array_column($form['fields'][0]['formats'], 'value') === ['basic_html', 'plain_text'], 'Formats are filtered by account permissions');
check(!isset($form['baseFields']->status, $form['baseFields']->promote, $form['baseFields']->sticky), 'Administrative base fields are not writable');
check($form['defaults']['state'] === 'published' && $form['states'] === [], 'Ordinary writer retains the content type default without a status override');
$orderedDisplay = EntityFormDisplay::load('node.blog.default');
$orderedDisplay->setComponent('field_hidden', ['type' => 'string_textfield', 'weight' => -20])->save();
check(array_column($service->node('blog', NULL, NULL)['fields'], 'key') === ['field_hidden', 'body'], 'Field descriptions retain display weight order');
$orderedDisplay->removeComponent('field_hidden')->save();
$node = Node::create(['type' => 'blog', 'uid' => $writer->id(), 'title' => 'Own blog',
  'body' => ['value' => '<p>Initial</p>', 'format' => 'basic_html']]); $node->save();
check($service->node('blog', $node->uuid(), NULL)['fields'][0]['textFormat'] === 'basic_html', 'Editing preserves the permitted original format');
account($other);
denied(fn() => $service->node('blog', $node->uuid(), NULL), 403, 'Other writers cannot describe private edit controls');
$edited = Node::create(['type' => 'blog', 'uid' => $editor->id(), 'title' => 'Editor-owned', 'body' => ['value' => 'Body', 'format' => 'basic_html']]); $edited->save();
account($editor);
check($service->types() === [], 'Edit-only account has no create options');
check(count($service->node('blog', $edited->uuid(), NULL)['fields']) === 1, 'Edit-only account can load its existing form');
denied(fn() => $service->node('blog', NULL, NULL), 403, 'Edit access does not imply create access');
account(new \Drupal\Core\Session\AnonymousUserSession());
denied(fn() => $service->types(), 403, 'Anonymous node discovery is denied');
account($writer);
FieldConfig::load('node.blog.body')->setSetting('allowed_formats', ['full_html'])->save();
denied(fn() => $service->node('blog', NULL, NULL), 403, 'A field with no permitted format cannot produce a writable form');
FieldConfig::load('node.blog.body')->setSetting('allowed_formats', [])->save();
// JSON:API aliases and disabled resources must apply to metadata too.
$resourceConfig = \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig::create([
  'id' => 'node--blog', 'disabled' => FALSE, 'path' => 'node/blog', 'resourceType' => 'node--blog',
  'resourceFields' => ['body' => ['fieldName' => 'body', 'publicName' => 'article_body', 'disabled' => FALSE]],
]);
$resourceConfig->save();
check($service->node('blog', NULL, NULL)['fields'][0]['key'] === 'article_body', 'Public field aliases are used in form descriptors');
$resourceConfig->set('resourceFields', ['body' => ['fieldName' => 'body', 'publicName' => 'body', 'disabled' => TRUE]])->save();
denied(fn() => $service->node('blog', NULL, NULL), 422, 'Disabled empty required field prevents an incomplete create form');
check($service->node('blog', $node->uuid(), NULL)['fields'] === [], 'Disabled populated fields remain unexposed during edit');
$resourceConfig->set('disabled', TRUE)->save();
denied(fn() => $service->node('blog', NULL, NULL), 403, 'Disabled JSON:API resources cannot expose forms');
$resourceConfig->delete();
// Field cardinality and term creation are not inferred from administrative configuration access.
\Drupal\taxonomy\Entity\Vocabulary::create(['vid' => 'form_tags', 'name' => 'Tags'])->save();
FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => 'field_tags', 'type' => 'entity_reference',
  'cardinality' => -1, 'settings' => ['target_type' => 'taxonomy_term']])->save();
FieldConfig::create(['entity_type' => 'node', 'bundle' => 'blog', 'field_name' => 'field_tags',
  'settings' => ['handler' => 'default:taxonomy_term', 'handler_settings' => [
    'target_bundles' => ['form_tags' => 'form_tags'], 'auto_create' => TRUE]]])->save();
$display = EntityFormDisplay::load('node.blog.default');
$display->setComponent('field_tags', ['type' => 'entity_reference_autocomplete'])->save();
$tag = array_values(array_filter($service->node('blog', NULL, NULL)['fields'], fn($field) => $field['key'] === 'field_tags'))[0];
check($tag['multiple'] && !$tag['autoCreate'], 'Multiple term selection does not grant term creation');
Role::load('writer')->grantPermission('create terms in form_tags')->save();
\Drupal::entityTypeManager()->getAccessControlHandler('taxonomy_term')->resetCache();
account(User::load($writer->id()));
$tag = array_values(array_filter($service->node('blog', NULL, NULL)['fields'], fn($field) => $field['key'] === 'field_tags'))[0];
check($tag['autoCreate'], 'Term auto-create requires both field configuration and current create access');
$display->removeComponent('field_tags')->save();
// Unsupported required controls fail explicitly rather than allowing a partial publication.
FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => 'field_multiple', 'type' => 'string', 'cardinality' => -1])->save();
FieldConfig::create(['entity_type' => 'node', 'bundle' => 'blog', 'field_name' => 'field_multiple', 'required' => TRUE])->save();
$display->setComponent('field_multiple', ['type' => 'string_textfield'])->save();
denied(fn() => $service->node('blog', NULL, NULL), 422, 'Unsupported empty required field prevents partial form');
$display->removeComponent('field_multiple')->save();
denied(fn() => $service->node('blog', NULL, NULL), 422, 'Hidden empty required field prevents an incomplete create form');
FieldConfig::load('node.blog.field_multiple')->delete();
// A workflow on one type does not turn unrelated types into moderated forms.
\Drupal::service('module_installer')->install(['content_moderation']);
NodeType::create(['type' => 'review', 'name' => 'Review'])->save();
EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => 'review', 'mode' => 'default', 'status' => TRUE])
  ->setComponent('title', ['type' => 'string_textfield'])->save();
\Drupal\workflows\Entity\Workflow::create(['id' => 'form_editorial', 'label' => 'Form editorial',
  'type' => 'content_moderation', 'type_settings' => [
    'states' => ['draft' => ['label' => 'Draft', 'published' => FALSE, 'default_revision' => FALSE, 'weight' => 0],
      'published' => ['label' => 'Published', 'published' => TRUE, 'default_revision' => TRUE, 'weight' => 1]],
    'transitions' => [
      'draft' => ['label' => 'Draft', 'from' => ['draft', 'published'], 'to' => 'draft', 'weight' => 0],
      'publish' => ['label' => 'Publish', 'from' => ['draft', 'published'], 'to' => 'published', 'weight' => 1]],
    'entity_types' => ['node' => ['review']], 'default_moderation_state' => 'draft',
  ]])->save();
Role::load('writer')->grantPermission('create review content')->grantPermission('use form_editorial transition draft')->save();
account(User::load($writer->id()));
$service = \Drupal::service('xinshi_api.content_forms');
$form = $service->node('review', NULL, NULL);
check($form['moderated'] && $form['defaults']['state'] === 'draft', 'Moderated creation uses workflow initial state');
check(array_column($form['states'], 'value') === ['draft'], 'Only permitted transitions become selectable states');
check(!isset($form['baseFields']->status), 'Moderated forms never offer the raw published flag');
check(!$service->node('blog', NULL, NULL)['moderated'], 'Unmoderated blog stays independent of installed workflow module');
Role::load('writer')->revokePermission('create review content')->save();
// Translation descriptions check current translation authority, not just a UUID.
\Drupal\language\Entity\ConfigurableLanguage::createFromLangcode('fr')->save();
\Drupal::service('content_translation.manager')->setEnabled('node', 'blog', TRUE);
account(User::load($writer->id()));
denied(fn() => $service->node('blog', $node->uuid(), 'fr'), 403, 'Node editing alone does not grant translation creation');
Role::load('writer')->grantPermission('translate blog node')->grantPermission('create content translations')->save();
account(User::load($writer->id()));
check(count($service->node('blog', $node->uuid(), 'fr')['fields']) === 1, 'Authorized new translation can obtain its form');
check(!Node::load($node->id())->hasTranslation('fr'), 'Describing a translation never saves it');
denied(fn() => $service->node('blog', NULL, 'invalid-language'), 400, 'Unknown language is rejected');
// A separately booted HTTP fixture checks real auth, JSON:API writes, and refusal cache headers.
echo "Content form service checks: $checks\n";
