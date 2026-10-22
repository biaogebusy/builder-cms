<?php

/**
 * @file
 * Installs only a fresh, guarded, network-isolated synthetic site.
 */

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_OFFICE_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') || $root !== '/app' ||
  file_exists('/tmp/office-test') || file_exists($root . '/docroot/sites/default/settings.php')) {
  throw new RuntimeException('Run in a fresh disposable source-copy container only.');
}
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
mkdir('/tmp/office-test');
mkdir('/tmp/office-test/private');
file_put_contents('sites/default/settings.php', <<<'SETTINGS'
<?php
$databases['default']['default'] = [
  'driver' => 'sqlite', 'database' => '/tmp/office-test/site.sqlite',
  'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
];
$settings['hash_salt'] = 'disposable-office-tests';
$settings['config_sync_directory'] = '/tmp/office-test/config';
$settings['file_private_path'] = '/tmp/office-test/private';
$settings['skip_permissions_hardening'] = TRUE;
$settings['extension_discovery_scan_tests'] = TRUE;
SETTINGS);
if (getenv('XINSHI_OFFICE_TEST_MYSQL') === '1') {
  file_put_contents('sites/default/settings.php', <<<'MYSQL'
$databases['default']['default'] = [
  'driver' => 'mysql', 'database' => 'office_test', 'username' => 'office',
  'password' => 'office-test-only', 'host' => 'office-db', 'port' => 3306,
  'namespace' => 'Drupal\mysql\Driver\Database\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/', 'prefix' => '',
];
MYSQL, FILE_APPEND);
}
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += ['HTTP_HOST' => 'office-test', 'SERVER_NAME' => 'office-test', 'SERVER_PORT' => 80,
  'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => $root . '/docroot/index.php',
  'REQUEST_METHOD' => 'GET', 'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'office-tests'];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'], 'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => [
    'site_name' => 'Office tests', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'fixture-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'test-only-password', 'pass2' => 'test-only-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE,
  ]],
]);
