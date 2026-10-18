<?php

/**
 * @file
 * Installs only a fresh, guarded, network-isolated synthetic site.
 */

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_CHAT_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') || $root !== '/app' ||
  file_exists('/tmp/chat-test') || file_exists($root . '/docroot/sites/default/settings.php')) {
  throw new RuntimeException('Run in a fresh disposable source-copy container only.');
}
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
mkdir('/tmp/chat-test');
mkdir('/tmp/chat-test/private');
file_put_contents('sites/default/settings.php', <<<'SETTINGS'
<?php
$databases['default']['default'] = [
  'driver' => 'sqlite', 'database' => '/tmp/chat-test/site.sqlite',
  'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
];
$settings['hash_salt'] = 'disposable-chat-tests';
$settings['config_sync_directory'] = '/tmp/chat-test/config';
$settings['file_private_path'] = '/tmp/chat-test/private';
$settings['skip_permissions_hardening'] = TRUE;
$settings['extension_discovery_scan_tests'] = TRUE;
$config['jsonapi.settings']['read_only'] = FALSE;
SETTINGS);
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += ['HTTP_HOST' => 'chat-test', 'SERVER_NAME' => 'chat-test', 'SERVER_PORT' => 80,
  'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => $root . '/docroot/index.php',
  'REQUEST_METHOD' => 'GET', 'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'chat-tests'];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'], 'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => [
    'site_name' => 'Chat tests', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'fixture-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'test-only-password', 'pass2' => 'test-only-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE,
  ]],
]);
