<?php

declare(strict_types=1);

/** Exercises real Drupal entities and permissions only in a disposable synthetic site. */
$root = dirname(__DIR__, 5);
if (getenv('XINSHI_MEDIA_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv')
  || $root !== '/app' || file_exists('/tmp/media-test')
  || file_exists($root . '/docroot/sites/default/settings.php')) {
  throw new RuntimeException('Use a fresh disposable container with a source copy at /app.');
}
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
mkdir('sites/default/files', 0777, TRUE);
mkdir('/tmp/media-test');
mkdir('/tmp/media-test/private');
file_put_contents('sites/default/settings.php', <<<'SETTINGS'
<?php
$databases['default']['default'] = [
  'driver' => 'sqlite', 'database' => '/tmp/media-test/site.sqlite',
  'namespace' => 'Drupal\sqlite\Driver\Database\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/', 'prefix' => '',
];
$settings['hash_salt'] = 'disposable-media-tests';
$settings['config_sync_directory'] = '/tmp/media-test/config';
$settings['skip_permissions_hardening'] = TRUE;
$settings['extension_discovery_scan_tests'] = TRUE;
$settings['file_private_path'] = '/tmp/media-test/private';
SETTINGS);
file_put_contents('sites/default/default.settings.php', '<?php');
file_put_contents('sites/default/default.services.yml', 'parameters: {}');
$_SERVER += ['HTTP_HOST' => 'media-test', 'SERVER_NAME' => 'media-test',
  'SERVER_PORT' => 80, 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php',
  'SCRIPT_FILENAME' => $root . '/docroot/index.php', 'REQUEST_METHOD' => 'GET',
  'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_USER_AGENT' => 'media-tests'];
require_once $root . '/docroot/core/includes/install.core.inc';
install_drupal($loader, [
  'parameters' => ['profile' => 'minimal', 'langcode' => 'en'], 'site_path' => 'sites/default',
  'forms' => ['install_configure_form' => [
    'site_name' => 'Media tests', 'site_mail' => 'test@example.test',
    'account' => ['name' => 'fixture-admin', 'mail' => 'test@example.test',
      'pass' => ['pass1' => 'test-only-password', 'pass2' => 'test-only-password']],
    'enable_update_status_module' => FALSE, 'enable_update_status_emails' => FALSE,
  ]],
]);
\Drupal::service('module_installer')->install(['xinshi_media', 'jsonapi', 'views']);
\Drupal::configFactory()->getEditable('jsonapi.settings')->set('read_only', FALSE)->save();
\Drupal::service('router.builder')->rebuild();
$checks = 0;
function check(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function denied(callable $run, string $code): void {
  try { $run(); }
  catch (\Drupal\xinshi_media\MediaException $error) {
    check($error->reason === $code, 'Denied: ' . $code);
    return;
  }
  throw new RuntimeException('Expected denial: ' . $code);
}
function account(\Drupal\Core\Session\AccountInterface $user): void {
  \Drupal::currentUser()->setAccount($user);
  foreach (['media', 'file'] as $type) \Drupal::entityTypeManager()->getAccessControlHandler($type)->resetCache();
}
$permissions = ['access content', 'view media', 'view own unpublished media', 'delete own files'];
foreach (['image', 'vector', 'document', 'audio', 'video'] as $bundle) {
  foreach (['create', 'edit own', 'delete own'] as $op) $permissions[] = $op . ' ' . $bundle . ' media';
}
\Drupal\user\Entity\Role::create(['id' => 'media_author', 'label' => 'Media author', 'permissions' => $permissions])->save();
\Drupal\user\Entity\Role::create(['id' => 'media_reader', 'label' => 'All media reader',
  'permissions' => ['access content', 'view media', 'view all media assets']])->save();
$users = [];
foreach (['alice', 'bob', 'reader'] as $name) {
  $user = \Drupal\user\Entity\User::create(['name' => $name, 'pass' => 'media-test-password',
    'status' => 1, 'roles' => [$name === 'reader' ? 'media_reader' : 'media_author']]);
  $user->save();
  $users[$name] = $user;
}
account($users['alice']);
$catalog = \Drupal::service('xinshi_media.catalog');
$upload = \Drupal::service('xinshi_media.upload');
$capabilities = $catalog->capabilities();
check(count($capabilities['types']) === 5, 'Discovers all five configured media kinds');
check(!$capabilities['canViewAll'], 'Author cannot browse all owners');
ob_start(); imagepng(imagecreatetruecolor(2, 2)); $png = ob_get_clean();
$image = $upload->create('image', '测试 image.png', $png);
check($image['uuid'] !== $image['fileId'], 'Media and file identities remain distinct');
check($image['mimeType'] === 'image/png', 'Detects actual raster MIME');
$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><script>alert(1)</script><foreignObject><p>bad</p></foreignObject><rect width="20" height="20" fill="red" onclick="bad()"/><use href="https://invalid.example/a.svg#x"/></svg>';
$vector = $upload->create('vector', 'logo.svg', $svg);
$file = $catalog->file($catalog->load($vector['uuid']));
$clean = file_get_contents($file->getFileUri());
check(!str_contains($clean, 'script') && !str_contains($clean, 'foreignObject') &&
  !str_contains($clean, 'onclick') && !str_contains($clean, 'https://'), 'SVG has no active or remote content');
$historical = '<svg xmlns="http://www.w3.org/2000/svg"><rect style="fill: red" width="20" height="20"/></svg>';
file_put_contents($file->getFileUri(), $historical);
$file->setFilename('historical.svg');
$file->save();
check(file_get_contents($file->getFileUri()) === $historical, 'Metadata save preserves historical SVG source bytes');
$document = $upload->create('document', 'notes.txt', 'Private document');
check($document['private'] && str_contains($document['source'], '/api/v3/media/'), 'Private files use an authorized endpoint');
denied(fn() => $upload->create('image', 'fake.png', '<svg/>'), 'invalid_image');
denied(fn() => $upload->create('vector', 'entity.svg', '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg/>'), 'invalid_svg');
denied(fn() => $upload->create('vector', 'huge.svg', str_repeat('x', 1048577)), 'file_too_large');
denied(fn() => $upload->create('document', 'app.php', '<?php'), 'unsupported_format');
denied(fn() => $upload->create('image', '../file.png', $png), 'invalid_filename');
check(count($catalog->listing([])['rows']) === 3, 'Author lists only readable own media');
check(count($catalog->listing(['purpose' => 'raster-image'])['rows']) === 1, 'Model picker excludes SVG and documents');
check(count($catalog->listing(['purpose' => 'web-image'])['rows']) === 2, 'Web picker includes raster and sanitized vectors');
account($users['bob']);
check($catalog->listing([])['rows'] === [], 'Other author cannot enumerate Alice assets');
denied(fn() => $catalog->load($image['uuid']), 'media_unavailable');
denied(fn() => $catalog->listing(['scope' => 'all']), 'permission_denied');
denied(fn() => $catalog->rename($image['uuid'], ['name' => 'stolen']), 'media_unavailable');
$bobImage = $upload->create('image', 'bob.png', $png);
account($users['reader']);
$rows = $catalog->listing([])['rows'];
check(count($rows) === 4, 'All-media reader can see both authors');
check(array_reduce($rows, fn($ok, $row) => $ok && !$row['canRename'] && !$row['canRemove'], TRUE), 'All-media read permission grants no writes');
denied(fn() => $catalog->rename($image['uuid'], ['name' => 'forbidden']), 'permission_denied');
account($users['alice']);
check($catalog->rename($image['uuid'], ['name' => 'Renamed'])['title'] === 'Renamed', 'Owner can rename');
$catalog->remove($image['uuid']);
check(count($catalog->listing([])['rows']) === 2, 'Removed media disappears from the library');
check($catalog->load($image['uuid'])->uuid() === $image['uuid'], 'Removal preserves existing media references');
check(is_file($catalog->file($catalog->load($image['uuid']))->getFileUri()), 'Removal preserves original file');
// Simulate a metadata request and verify core access without using the custom controller.
$request = \Symfony\Component\HttpFoundation\Request::create('/jsonapi/media/image');
$request->attributes->set('_route', 'jsonapi.media--image.collection');
\Drupal::requestStack()->push($request);
account($users['bob']);
check(!\Drupal::entityTypeManager()->getStorage('media')->load($image['id'])->access('view'), 'JSON:API entity view cannot bypass author scope');
$ids = \Drupal::entityTypeManager()->getStorage('media')->getQuery()->accessCheck(TRUE)->condition('bundle', 'image')->execute();
check(count($ids) === 1 && in_array($bobImage['id'], $ids), 'JSON:API SQL scope is applied before pagination');
account($users['alice']);
check(!\Drupal::entityTypeManager()->getStorage('media')->load($image['id'])->access('delete'), 'Metadata APIs cannot physically delete referenced media');
// Exercise a cached legacy View as two accounts with the same role permissions.
\Drupal\views\Entity\View::create(['id' => 'media_scope_fixture', 'label' => 'Media scope fixture',
  'base_table' => 'media_field_data', 'base_field' => 'mid', 'display' => ['default' => [
    'id' => 'default', 'display_plugin' => 'default', 'display_options' => [
      'fields' => ['mid' => ['id' => 'mid', 'table' => 'media_field_data', 'field' => 'mid', 'plugin_id' => 'field']],
      'pager' => ['type' => 'none'], 'cache' => ['type' => 'time', 'options' => ['results_lifespan' => 3600, 'output_lifespan' => 3600]],
    ],
  ]], 'status' => TRUE])->save();
\Drupal::requestStack()->push(\Symfony\Component\HttpFoundation\Request::create('/api/v2/media'));
$cacheKeys = [];
foreach ([['alice', 3], ['bob', 1], ['alice', 3]] as [$name, $expected]) {
  account($users[$name]);
  $view = \Drupal\views\Views::getView('media_scope_fixture');
  $view->execute();
  check(count($view->result) === $expected, 'Cached legacy View retains owner scope: ' . $name);
  $cacheKeys[] = $view->display_handler->getPlugin('cache')->generateResultsKey();
  $view->destroy();
}
check($cacheKeys[0] !== $cacheKeys[1], 'Legacy View result cache separates equal-role owners');
\Drupal::requestStack()->pop();
\Drupal::requestStack()->pop();
account(new \Drupal\Core\Session\AnonymousUserSession());
\Drupal\user\Entity\Role::load('anonymous')->grantPermission('view media')->save();
check(\Drupal::entityTypeManager()->getStorage('media')->load($vector['id'])->access('view'), 'Normal published-page entity rendering remains available');
check(!\Drupal::entityTypeManager()->getStorage('media')->load($document['id'])->access('view'), 'Private media does not become public with published status');
// A new upload allowlist must not narrow historical image display formats.
$historicalFile = \Drupal::entityTypeManager()->getStorage('media')->load($image['id'])->get('field_media_image')->entity;
$historicalFile->setMimeType('image/bmp');
$historicalFile->save();
check($catalog->display($image['uuid'])['src'] === $image['source'], 'Historical image MIME remains displayable outside the new upload allowlist');
$historicalFile->setMimeType('image/png');
$historicalFile->save();
// Scan bounds must not imply that a filtered empty page is the end of the collection.
account($users['alice']);
for ($i = 0; $i < 242; $i++) {
  $copy = \Drupal::entityTypeManager()->getStorage('media')->load($image['id'])->createDuplicate();
  $copy->setName('Cursor fixture ' . $i)->save();
}
$empty = $catalog->listing(['name' => 'no matching title']);
check($empty['rows'] === [] && $empty['nextCursor'] !== NULL, 'Empty scan retains a continuation cursor');
check($catalog->listing(['name' => 'no matching title', 'cursor' => $empty['nextCursor']])['nextCursor'] === NULL, 'Cursor progresses to the final scan');
// Keep the HTTP fixture small without physically deleting referenced media or files.
foreach (\Drupal::entityTypeManager()->getStorage('media')->getQuery()->accessCheck(FALSE)
  ->condition('name', 'Cursor fixture%', 'LIKE')->execute() as $mid) {
  \Drupal::database()->insert('xinshi_media_hidden')->fields(['mid' => $mid, 'uid' => $users['alice']->id(), 'removed' => time()])->execute();
}
file_put_contents('/tmp/media-test/fixtures.json', json_encode(['image' => $image, 'vector' => $vector, 'document' => $document, 'bob' => $bobImage]));
echo 'Media integration checks: ' . $checks . PHP_EOL;
