<?php

/**
 * @file
 * Installs only a fresh, guarded, network-isolated synthetic site.
 */

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_SHOOTING_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') || $root !== '/app' ||
  file_exists('/tmp/shooting-test') || file_exists($root . '/docroot/sites/default/settings.php')) {
  throw new RuntimeException('Run in a fresh disposable source-copy container only.');
}
$loader = require $root . '/vendor/autoload.php';
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
mkdir('/tmp/shooting-test');
file_put_contents('sites/default/settings.php', <<<'SETTINGS'
<?php
$databases['default']['default'] = [
  'driver' => 'sqlite', 'database' => '/tmp/shooting-test/site.sqlite',
  'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
];
$settings['hash_salt'] = 'disposable-shooting-tests';
$settings['config_sync_directory'] = '/tmp/shooting-test/config';
$settings['skip_permissions_hardening'] = TRUE;
$settings['extension_discovery_scan_tests'] = TRUE;
$config['jsonapi.settings']['read_only'] = FALSE;
SETTINGS);
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += ['HTTP_HOST' => 'shooting-test', 'SERVER_NAME' => 'shooting-test', 'SERVER_PORT' => 80,
  'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => $root . '/docroot/index.php',
  'REQUEST_METHOD' => 'GET', 'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'shooting-tests'];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'], 'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => [
    'site_name' => 'Shooting tests', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'fixture-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'test-only-password', 'pass2' => 'test-only-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE,
  ]],
]);
\Drupal::service('module_installer')->install(['xinshi_shooting_plan_test']);
\Drupal::database()->schema()->createTable('xinshi_shooting_sketch', xinshi_shooting_plan_schema()['xinshi_shooting_sketch']);

// Install the shipped plan schema in the synthetic site without loading AI providers.
$source = new \Drupal\Core\Config\FileStorage(dirname(__DIR__) . '/config/install');
\Drupal\node\Entity\NodeType::create($source->read('node.type.shooting_plan'))->save();
\Drupal\field\Entity\FieldStorageConfig::create($source->read('field.storage.node.field_shooting_document'))->save();
\Drupal\field\Entity\FieldConfig::create($source->read('field.field.node.shooting_plan.field_shooting_document'))->save();
\Drupal\Core\Field\Entity\BaseFieldOverride::create($source->read('core.base_field_override.node.shooting_plan.status'))->save();
\Drupal\field\Entity\FieldStorageConfig::create($source->read('field.storage.node.field_shooting_references'))->save();
\Drupal\field\Entity\FieldConfig::create($source->read('field.field.node.shooting_plan.field_shooting_references'))->save();
