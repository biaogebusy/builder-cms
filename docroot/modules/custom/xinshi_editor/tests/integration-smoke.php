<?php

declare(strict_types=1);

/**
 * @file
 * Installs a disposable SQLite site for Views/editor dependency checks.
 *
 * Run only in an empty container with a source copy at /app, no site mounts,
 * and XINSHI_EDITOR_ISOLATED_TEST=1. Never point this runner at a real site.
 */

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_EDITOR_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    $root !== '/app' || file_exists($root . '/docroot/sites/default/settings.php') ||
    file_exists('/tmp/xinshi-editor-test')) {
  throw new RuntimeException('Use a fresh, isolated test container with a source copy at /app.');
}
require __DIR__ . '/bootstrap.php';
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
mkdir('/tmp/xinshi-editor-test');
file_put_contents('sites/default/settings.php', <<<'PHP'
<?php
$databases['default']['default'] = [
  'driver' => 'sqlite', 'database' => '/tmp/xinshi-editor-test/site.sqlite',
  'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
];
$settings['hash_salt'] = 'isolated-editor-integration-tests-only';
$settings['config_sync_directory'] = '/tmp/xinshi-editor-test/config';
$settings['skip_permissions_hardening'] = TRUE;
PHP);
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += [
  'HTTP_HOST' => 'editor-test', 'SERVER_NAME' => 'editor-test', 'SERVER_PORT' => 80,
  'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => $root . '/docroot/index.php',
  'REQUEST_METHOD' => 'GET', 'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'editor-tests',
];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'],
  'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => [
    'site_name' => 'Editor integration tests', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'test-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'test-only-password', 'pass2' => 'test-only-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE,
  ]],
]);
\Drupal::service('module_installer')->install(['xinshi_views', 'xinshi_editor']);

function check(bool $condition, string $description): void {
  if (!$condition) {
    throw new RuntimeException($description);
  }
  echo 'PASS: ' . $description . "\n";
}

foreach (['views', 'rest', 'serialization', 'editor', 'file', 'text'] as $module) {
  check(\Drupal::moduleHandler()->moduleExists($module), 'Required module enabled: ' . $module);
}
foreach (['block_content', 'ace_editor', 'ckeditor_uploadimage', 'xinshi_ai'] as $module) {
  check(!\Drupal::moduleHandler()->moduleExists($module), 'Optional module remains disabled: ' . $module);
}
$blockDefinitions = \Drupal::service('plugin.manager.block')->getDefinitions();
check($blockDefinitions !== [], 'Block discovery succeeds without Block Content');
$typed = \Drupal::service('config.typed');
check(!array_key_exists('block.settings.block_content:*', $typed->getDefinitions()),
  'No Block Content schema created while module is absent');
\Drupal::service('event_dispatcher')->addSubscriber(new \Drupal\Core\Config\Development\ConfigSchemaChecker($typed));
\Drupal\filter\Entity\FilterFormat::create(['format' => 'site_json', 'name' => 'Site JSON', 'filters' => []])->save();
$editor = \Drupal\editor\Entity\Editor::create(['format' => 'site_json', 'editor' => 'json_editor']);
$editor->save();
$jsonEditor = \Drupal::service('plugin.manager.editor')->createInstance('json_editor');
check($jsonEditor->getJsSettings($editor)['mode'] === 'code', 'New editor entity produces a valid default mode');
check(\Drupal::service('plugin.manager.field.formatter')->getDefinition('json_foramtter')['class'] ===
  \Drupal\xinshi_editor\Plugin\Field\FieldFormatter\JsonFormatter::class, 'Existing formatter plugin ID is discoverable');

foreach (['rest_export_first_row', 'rest_export_inner_nested'] as $id) {
  check(\Drupal::service('plugin.manager.views.display')->createInstance($id)->getPluginId() === $id,
    'REST display instantiates: ' . $id);
}
check(\Drupal::service('plugin.manager.views.style')->createInstance('xinshi_pager_serializer')->getPluginId() ===
  'xinshi_pager_serializer', 'Pager serializer instantiates');

// Add Block Content only after checking the optional-module boundary above.
\Drupal::service('module_installer')->install(['block_content']);
$typed = \Drupal::service('config.typed');
\Drupal::service('event_dispatcher')->addSubscriber(new \Drupal\Core\Config\Development\ConfigSchemaChecker($typed));
\Drupal\block_content\Entity\BlockContentType::create(['id' => 'test_component', 'label' => 'Test component'])->save();
$component = \Drupal\block_content\Entity\BlockContent::create(['type' => 'test_component', 'info' => 'Original']);
$component->save();
$revision = (int) $component->getRevisionId();
$component->setNewRevision(TRUE);
$component->setInfo('Changed');
$component->save();
$plugin = 'block_content:' . $component->uuid();
\Drupal::service('plugin.manager.block')->clearCachedDefinitions();
check(\Drupal::service('plugin.manager.block')->getDefinition($plugin)['class'] ===
  \Drupal\xinshi_editor\Plugin\Block\BlockContentBlock::class, 'Existing content-block override is discovered');
$placement = \Drupal\block\Entity\Block::create([
  'id' => 'site_component', 'theme' => 'stark', 'region' => 'content', 'plugin' => $plugin,
  'settings' => ['label' => 'Revision test', 'label_display' => '0', 'view_mode' => 'full', 'vid' => $revision],
]);
$placement->save();
\Drupal::entityTypeManager()->getStorage('block')->resetCache();
$reloaded = \Drupal\block\Entity\Block::load('site_component');
check($reloaded->getPlugin()->getConfiguration()['vid'] === $revision, 'Config entity save/reload preserves the selected revision');
check($reloaded->getPlugin()->getBlockContentEntity()->getRevisionId() == $revision,
  'Existing block loading still selects the stored revision');
echo "Isolated Views/editor installation checks passed.\n";
