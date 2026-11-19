<?php

/**
 * @file
 * Installs a fresh local SQLite fixture without accepting a site database or settings.
 */

$root = dirname(__DIR__, 6);
if (getenv('XINSHI_SUPPLIER_ISOLATED_TEST') !== '1' ||
  !str_starts_with(basename($root), 'xinshi-supplier-tests-') ||
  file_get_contents($root . '/.supplier-fixture') !== 'synthetic-only' ||
  is_file($root . '/docroot/sites/default/settings.php') || is_dir($root . '/fixture-data')) {
  throw new RuntimeException('Use the supplier source-copy runner with a fresh fixture.');
}
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
require_once $root . '/docroot/core/includes/bootstrap.inc';
mkdir($root . '/fixture-data/private', 0777, TRUE);
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
file_put_contents('sites/default/settings.php', '<?php' . "\n" .
  '$databases["default"]["default"] = ' . var_export([
    'driver' => 'sqlite', 'database' => $root . '/fixture-data/site.sqlite',
    'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
  ], TRUE) . ";\n" . '$settings = ' . var_export([
    'hash_salt' => 'synthetic-supplier-salt', 'config_sync_directory' => $root . '/fixture-data/config',
    'file_private_path' => $root . '/fixture-data/private', 'skip_permissions_hardening' => TRUE,
    'xinshi_ai_service.secrets' => ['chat-node' => 'synthetic-service-secret'],
  ], TRUE) . ";\n");
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += ['HTTP_HOST' => 'supplier.test', 'SERVER_NAME' => 'supplier.test', 'SERVER_PORT' => 80,
  'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => $root . '/docroot/index.php',
  'REQUEST_METHOD' => 'GET', 'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'supplier-tests'];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'], 'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => ['site_name' => 'Supplier fixture', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'fixture-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'synthetic-password', 'pass2' => 'synthetic-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE]],
]);
