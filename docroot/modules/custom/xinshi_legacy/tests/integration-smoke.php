<?php

declare(strict_types=1);

/**
 * @file
 * Installs a disposable SQLite site for legacy/migration dependency checks.
 *
 * Run only in an empty container with a source copy at /app, no site mounts,
 * and XINSHI_LEGACY_ISOLATED_TEST=1. Never point this runner at a real site.
 */

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_LEGACY_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    $root !== '/app' || file_exists($root . '/docroot/sites/default/settings.php') ||
    file_exists('/tmp/xinshi-legacy-test')) {
  throw new RuntimeException('Use a fresh, isolated test container with a source copy at /app.');
}
require __DIR__ . '/bootstrap.php';
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
mkdir('/tmp/xinshi-legacy-test');
file_put_contents('sites/default/settings.php', <<<'PHP'
<?php
$databases['default']['default'] = [
  'driver' => 'sqlite', 'database' => '/tmp/xinshi-legacy-test/site.sqlite',
  'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
];
$settings['hash_salt'] = 'isolated-legacy-integration-tests-only';
$settings['config_sync_directory'] = '/tmp/xinshi-legacy-test/config';
$settings['skip_permissions_hardening'] = TRUE;
PHP);
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += [
  'HTTP_HOST' => 'legacy-test', 'SERVER_NAME' => 'legacy-test', 'SERVER_PORT' => 80,
  'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => $root . '/docroot/index.php',
  'REQUEST_METHOD' => 'GET', 'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'legacy-tests',
];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'],
  'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => [
    'site_name' => 'Legacy integration tests', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'test-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'test-only-password', 'pass2' => 'test-only-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE,
  ]],
]);
\Drupal::service('module_installer')->install(['xinshi_legacy', 'xinshi_migrate', 'update_to_d11']);

function check(bool $condition, string $description): void {
  if (!$condition) {
    throw new RuntimeException($description);
  }
  echo 'PASS: ' . $description . "\n";
}

foreach (['media', 'media_library', 'content_moderation', 'views', 'migrate',
  'block_content', 'editor', 'field', 'filter', 'node', 'layout_builder',
  'layout_builder_at', 'eck'] as $module) {
  check(\Drupal::moduleHandler()->moduleExists($module), 'Required module enabled: ' . $module);
}
foreach (['migrate_tools', 'migrate_plus', 'ckeditor5', 'ckeditor5_plugin_pack', 'xinshi_ai'] as $module) {
  check(!\Drupal::moduleHandler()->moduleExists($module), 'Optional module remains disabled: ' . $module);
}
foreach (['xinshi_media_library_widget', 'xinshi_moderation_state_button'] as $id) {
  check(\Drupal::service('plugin.manager.field.widget')->getDefinition($id)['provider'] === 'xinshi_legacy',
    'Legacy widget is discoverable: ' . $id);
}
check(\Drupal::service('update_to_d11.panelizer_migrator') instanceof \Drupal\update_to_d11\PanelizerMigrator,
  'Panelizer service constructs without running a migration');
check(\Drupal::service('update_to_d11.ckeditor4_migrator') instanceof \Drupal\update_to_d11\Ckeditor4Migrator,
  'Editor service constructs while CKEditor 5 remains disabled');
$database = \Drupal::database();
check($database->schema()->tableExists('xinshi_migrate_rows'), 'Migration ownership table installed');
check($database->schema()->tableExists('wechat_user'), 'Legacy account-binding table installed');
$binding = \Drupal::entityTypeManager()->getStorage('wechat_user')->create([
  'openid' => 'isolated-test-binding', 'uid' => 1, 'nickname' => 'Test binding',
]);
$binding->save();
$binding = \Drupal::entityTypeManager()->getStorage('wechat_user')->loadUnchanged($binding->id());
check($binding->get('openid')->value === 'isolated-test-binding' && (int) $binding->get('uid')->target_id === 1,
  'Legacy binding persists without the old WeChat service');
$plan = \Drupal::service('xinshi_migrate.plan');
check($plan->tables() === [], 'Missing private plan produces no tables');
$definitions = \Drupal::service('plugin.manager.migration')->getDefinitions();
check(!array_filter(array_keys($definitions), static fn(string $id): bool => str_starts_with($id, 'xinshi_snapshot')),
  'Missing private plan produces no snapshot migration derivatives');
foreach (['source', 'destination'] as $type) {
  check(\Drupal::service('plugin.manager.migrate.' . $type)->getDefinition('xinshi_snapshot')['provider'] === 'xinshi_migrate',
    'Snapshot plugin is discoverable: ' . $type);
}
try {
  $plan->validateTarget();
  throw new LogicException('A missing plan must not authorize a migration.');
}
catch (RuntimeException $exception) {
  check($exception->getMessage() === 'The migration plan does not match this CLI target site.',
    'Missing plan fails target validation before opening a source connection');
}
check((int) $database->select('xinshi_migrate_rows')->countQuery()->execute()->fetchField() === 0,
  'Installation and discovery leave the migration ledger empty');

// Exercise dynamic widget schema selection through a real config entity save.
$typed = \Drupal::service('config.typed');
\Drupal::service('event_dispatcher')->addSubscriber(new \Drupal\Core\Config\Development\ConfigSchemaChecker($typed));
\Drupal\node\Entity\NodeType::create(['type' => 'legacy_test', 'name' => 'Legacy test'])->save();
\Drupal\field\Entity\FieldStorageConfig::create([
  'entity_type' => 'node', 'field_name' => 'field_legacy_media', 'type' => 'entity_reference',
  'settings' => ['target_type' => 'media'],
])->save();
\Drupal\field\Entity\FieldConfig::create([
  'entity_type' => 'node', 'bundle' => 'legacy_test', 'field_name' => 'field_legacy_media', 'label' => 'Media',
])->save();
$settings = \Drupal\xinshi_legacy\Plugin\Field\FieldWidget\MediaLibraryWidget::defaultSettings();
$settings['width'] = '900px';
$settings['edit'] = 0;
$display = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'legacy_test');
$display->setComponent('field_legacy_media', ['type' => 'xinshi_media_library_widget', 'settings' => $settings]);
$display->save();
$display = \Drupal::entityTypeManager()->getStorage('entity_form_display')->loadUnchanged($display->id());
$saved = $display->getComponent('field_legacy_media')['settings'];
check($saved['width'] === '900px' && $saved['edit'] === FALSE && $saved['media_types'] === [],
  'Form display save/reload preserves custom and inherited widget settings');
check($display->getRenderer('field_legacy_media') instanceof \Drupal\xinshi_legacy\Plugin\Field\FieldWidget\MediaLibraryWidget,
  'Saved form display instantiates the legacy media widget');
echo "Isolated legacy/migration installation checks passed.\n";
