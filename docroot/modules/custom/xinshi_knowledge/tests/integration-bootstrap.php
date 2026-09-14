<?php

declare(strict_types=1);

/** Install a disposable site only inside the dedicated test container. */
if (getenv('XINSHI_KNOWLEDGE_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    !is_dir('/tmp/xinshi-knowledge-test') || is_file('/site/docroot/sites/default/settings.php')) {
  throw new RuntimeException('Use an empty, isolated test container; never a real site.');
}
require __DIR__ . '/bootstrap.php';
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
mkdir('/tmp/xinshi-knowledge-test/private');
$settings = <<<'PHP'
<?php
$databases['default']['default'] = [
  'driver' => 'sqlite', 'database' => '/tmp/xinshi-knowledge-test/site.sqlite',
  'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
];
$settings['hash_salt'] = 'isolated-knowledge-integration-tests-only';
$settings['file_private_path'] = '/tmp/xinshi-knowledge-test/private';
$settings['file_public_path'] = 'sites/default/files';
$settings['config_sync_directory'] = '/tmp/xinshi-knowledge-test/config';
$settings['skip_permissions_hardening'] = TRUE;
PHP;
file_put_contents('sites/default/settings.php', $settings);
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += ['HTTP_HOST' => 'knowledge-test', 'SERVER_NAME' => 'knowledge-test',
  'SERVER_PORT' => 80, 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php',
  'SCRIPT_FILENAME' => $root . '/docroot/index.php', 'REQUEST_METHOD' => 'GET',
  'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'knowledge-tests'];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'],
  'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => [
    'site_name' => 'Knowledge integration tests', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'test-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'test-only-password', 'pass2' => 'test-only-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE,
  ]],
]);
\Drupal::service('module_installer')->install(['xinshi_knowledge', 'xinshi_knowledge_test_access']);
\Drupal::service('router.builder')->rebuild();
\Drupal::configFactory()->getEditable('xinshi_knowledge.settings')
  ->set('tika_url', 'http://127.0.0.1:9998')->save();
fwrite(STDOUT, "Disposable Drupal site and knowledge module installed.\n");
