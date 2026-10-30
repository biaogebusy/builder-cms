<?php

declare(strict_types=1);

/** Exercises real Drupal entities and permissions only in a disposable synthetic site. */
$root = dirname(__DIR__, 5);
if (getenv('XINSHI_CREDITS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv')
  || $root !== '/app' || file_exists('/tmp/credits-test')
  || file_exists($root . '/docroot/sites/default/settings.php')) {
  throw new RuntimeException('Use a fresh disposable container with a source copy at /app.');
}
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
mkdir('/tmp/credits-test');
mkdir('/tmp/credits-test/private');
file_put_contents('sites/default/settings.php', <<<'SETTINGS'
<?php
$databases['default']['default'] = [
  'driver' => 'sqlite', 'database' => '/tmp/credits-test/site.sqlite',
  'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
];
$settings['hash_salt'] = 'disposable-credits-tests';
$settings['config_sync_directory'] = '/tmp/credits-test/config';
$settings['skip_permissions_hardening'] = TRUE;
$settings['extension_discovery_scan_tests'] = TRUE;
$settings['file_private_path'] = '/tmp/credits-test/private';
SETTINGS);
if (getenv('CREDITS_TEST_MYSQL') === '1') {
  file_put_contents('sites/default/settings.php', <<<'MYSQL'

$databases['default']['default'] = [
  'driver' => 'mysql', 'database' => 'credits_test', 'username' => 'credits_test',
  'password' => 'isolated-test', 'host' => '127.0.0.1', 'port' => '3306',
  'namespace' => 'Drupal\mysql\Driver\Database\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/', 'prefix' => '',
];
MYSQL, FILE_APPEND);
}
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += ['HTTP_HOST' => 'credits-test', 'SERVER_NAME' => 'credits-test',
  'SERVER_PORT' => 80, 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php',
  'SCRIPT_FILENAME' => $root . '/docroot/index.php', 'REQUEST_METHOD' => 'GET',
  'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'credits-tests'];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'], 'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => [
    'site_name' => 'Credits tests', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'fixture-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'test-only-password', 'pass2' => 'test-only-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE,
  ]],
]);
\Drupal::service('module_installer')->install(['xinshi_credits']);
file_put_contents('/tmp/credits-test/installed', 'synthetic');
require __DIR__ . '/ledger.php';
