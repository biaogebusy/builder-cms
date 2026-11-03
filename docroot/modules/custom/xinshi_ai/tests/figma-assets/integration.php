<?php

declare(strict_types=1);

/**
 * @file
 * Real media storage and receipt transactions in a disposable synthetic Drupal site.
 */

use Drupal\Core\DrupalKernel;
use Drupal\media\Entity\MediaType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_ai\Controller\FigmaAssetController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

if (getenv('XINSHI_CHAT_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') || dirname(__DIR__, 6) !== '/app') {
  throw new RuntimeException('Use a disposable source-copy container.');
}
$complete = FALSE;
register_shutdown_function(static function () use (&$complete): void {
  if (!$complete) { exit(1); }
});
$loader = require '/app/vendor/autoload.php';
$module = dirname(__DIR__, 2);
$loader->addPsr4('Drupal\\xinshi_ai\\', $module . '/src');
$loader->addPsr4('Drupal\\ai\\', '/app/docroot/modules/contrib/ai/src');
$loader->addPsr4('Drupal\\Core\\', '/app/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', '/app/docroot/core/lib/Drupal/Component');
if (PHP_SAPI === 'cli-server') {
  require_once '/app/docroot/core/includes/bootstrap.inc';
  chdir('/app/docroot');
  $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
  $request = Request::createFromGlobals();
  $kernel = DrupalKernel::createFromRequest($request, $loader, 'prod');
  $response = $kernel->handle($request);
  $response->send();
  $kernel->terminate($request, $response);
  $complete = TRUE;
  return;
}
if (($argv[1] ?? '') === 'worker') {
  require_once '/app/docroot/core/includes/bootstrap.inc';
  chdir('/app/docroot');
  $request = Request::create('http://chat-test/');
  $kernel = DrupalKernel::createFromRequest($request, $loader, 'prod', FALSE);
  $kernel->boot();
  $kernel->preHandle($request);
  \Drupal::currentUser()->setAccount(User::load($argv[2]));
  $binary = file_get_contents('/tmp/chat-test/concurrent.png');
  file_put_contents('/tmp/chat-test/worker-' . (getenv('FIGMA_TEST_PAUSE') === '1' ? 'first' : 'second'),
    (string) \Drupal::database()->query('SELECT CONNECTION_ID()')->fetchField());
  $response = upload($binary);
  echo json_encode(['status' => $response->getStatusCode(), 'data' => json_decode($response->getContent(), TRUE)]);
  $complete = TRUE;
  exit;
}
if (file_exists('/tmp/chat-test')) {
  throw new RuntimeException('The fixture must be new.');
}
$testModule = '/app/docroot/modules/custom/figma_asset_test';
mkdir($testModule);
file_put_contents($testModule . '/figma_asset_test.info.yml', Yaml::dump([
  'name' => 'Figma asset tests', 'type' => 'module', 'core_version_requirement' => '^11',
  'dependencies' => ['media', 'image', 'simple_oauth'],
]));
$config = Yaml::parseFile($module . '/xinshi_ai.services.yml')['services'];
file_put_contents($testModule . '/figma_asset_test.services.yml', Yaml::dump(['services' =>
  array_intersect_key($config, array_flip(['xinshi_ai.figma_assets', 'xinshi_ai.media_upload',
    'xinshi_ai.image_validator', 'logger.channel.xinshi_ai']))], 8));
$routes = Yaml::parseFile($module . '/xinshi_ai.routing.yml');
file_put_contents($testModule . '/figma_asset_test.routing.yml', Yaml::dump([
  'xinshi_ai.figma.assets' => $routes['xinshi_ai.figma.assets']], 8));
copy(__DIR__ . '/access.module', $testModule . '/figma_asset_test.module');
require dirname(__DIR__, 3) . '/xinshi_chat/tests/install-fixture.php';
\Drupal::service('module_installer')->install(['figma_asset_test']);
\Drupal::logger('xinshi_ai')->addLogger(new class extends \Psr\Log\AbstractLogger {
  public function log($level, \Stringable|string $message, array $context = []): void {
    fwrite(STDERR, $message . ' ' . json_encode($context) . PHP_EOL);
  }
});
$type = MediaType::create(['id' => 'image', 'label' => 'Image', 'source' => 'image']);
$type->save();
$field = $type->getSource()->createSourceField($type);
$field->getFieldStorageDefinition()->save();
$field->save();
$type->set('source_configuration', ['source_field' => $field->getName()])->save();
Role::create(['id' => 'figma_reader', 'label' => 'Media reader',
  'permissions' => ['view media', 'access content']])->save();
Role::load('authenticated')->revokePermission('view media')->revokePermission('access content')->save();
$users = [];
foreach (['alice', 'bob', 'limited'] as $name) {
  $users[$name] = User::create(['name' => $name, 'status' => 1,
    'roles' => $name === 'limited' ? [] : ['figma_reader']]);
  $users[$name]->save();
}
\Drupal::currentUser()->setAccount($users['alice']);
$checks = 0;
function check(bool $condition, string $message): void {
  if (!$condition) { throw new RuntimeException($message); }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function png(int $color): string {
  $image = imagecreatetruecolor(2, 2);
  imagefill($image, 0, 0, $color);
  ob_start();
  imagepng($image);
  $binary = ob_get_clean();
  imagedestroy($image);
  return $binary;
}
function upload(string $binary, ?string $digest = NULL, string $importId = '11111111-1111-4111-8111-111111111111') {
  $digest ??= hash('sha256', $binary);
  $request = Request::create('/api/v3/ai/figma/assets/' . $importId . '/' . $digest, 'PUT', [], [], [], [],
    json_encode(['filename' => 'figma.png', 'mimeType' => 'image/png', 'data' => base64_encode($binary)]));
  return FigmaAssetController::create(\Drupal::getContainer())->upload($request, $digest, $importId);
}
function counts(): array {
  return array_map(static fn($table) => (int) \Drupal::database()->select($table)->countQuery()->execute()->fetchField(),
    ['media', 'file_managed']);
}
function resetAccess(): void {
  foreach (['media', 'file'] as $type) {
    \Drupal::entityTypeManager()->getAccessControlHandler($type)->resetCache();
    \Drupal::entityTypeManager()->getStorage($type)->resetCache();
  }
  clearstatcache();
}
function media(): \Drupal\media\MediaInterface {
  $storage = \Drupal::entityTypeManager()->getStorage('media');
  $id = $storage->getQuery()->accessCheck(FALSE)->condition('uid', \Drupal::currentUser()->id())
    ->sort('mid', 'DESC')->range(0, 1)->execute();
  return $storage->load(reset($id));
}
$binary = png(0x123456);
$response = upload($binary);
check($response->getStatusCode() === 200, 'Initial import succeeds with actual media storage');
$first = json_decode($response->getContent(), TRUE);
check($first['digest'] === hash('sha256', $binary) && str_starts_with($first['url'], 'http'), 'Receipt binds bytes and URL');
check($response->headers->hasCacheControlDirective('no-store'), 'Successful receipt cannot be cached');
check(counts() === [1, 1], 'One media and one managed file are committed');
$again = upload($binary);
check($again->getContent() === $response->getContent() && counts() === [1, 1], 'Lost acknowledgement retry reuses the receipt');
check(upload($binary, str_repeat('0', 64))->getStatusCode() === 400 && counts() === [1, 1], 'Digest mismatch fails before writing');
check(upload('not an image')->getStatusCode() === 503 && counts() === [1, 1], 'Existing raster validation remains enforced');
\Drupal::currentUser()->setAccount($users['bob']);
$other = upload($binary);
check($other->getStatusCode() === 200 && $other->getContent() !== $response->getContent() && counts() === [2, 2],
  'The same bytes have separate account-owned imports');
\Drupal::currentUser()->setAccount($users['alice']);
putenv('FIGMA_TEST_DENY=1');
resetAccess();
check(upload($binary)->getStatusCode() === 409 && counts() === [2, 2], 'Current media denial blocks receipt reuse');
putenv('FIGMA_TEST_DENY');
resetAccess();
foreach (['FIGMA_TEST_FIELD_DENY', 'FIGMA_TEST_FILE_DENY'] as $denial) {
  putenv($denial . '=1');
  resetAccess();
  check(upload($binary)->getStatusCode() === 409 && counts() === [2, 2], 'Current access is checked: ' . $denial);
  putenv($denial);
}
resetAccess();
$original = media();
$original->setOwnerId($users['bob']->id())->save();
resetAccess();
check(upload($binary)->getStatusCode() === 409 && counts() === [2, 2], 'Changed ownership does not reuse the original receipt');
$original->setOwnerId($users['alice']->id())->save();
$file = $original->get('field_media_image')->entity;
file_put_contents($file->getFileUri(), str_repeat('x', strlen($binary)));
resetAccess();
check(upload($binary)->getStatusCode() === 409 && counts() === [2, 2], 'Replaced file bytes never reuse or recreate an import');
file_put_contents($file->getFileUri(), $binary);
$original->delete();
resetAccess();
check(upload($binary)->getStatusCode() === 409 && counts() === [1, 2], 'Deleted media leaves a receipt that prevents recreation');
check(upload($binary, NULL, '22222222-2222-4222-8222-222222222222')->getStatusCode() === 200 &&
  counts() === [2, 3], 'A distinct explicit import can create the same bytes after the prior media was deleted');
$before = counts();
$receipts = \Drupal::keyValue('xinshi_ai.figma_assets')->getAll();
putenv('FIGMA_TEST_ROLLBACK=1');
check(upload(png(0x456789))->getStatusCode() === 503, 'Post-insert failure is reported');
putenv('FIGMA_TEST_ROLLBACK');
resetAccess();
check(counts() === $before && \Drupal::keyValue('xinshi_ai.figma_assets')->getAll() === $receipts,
  'Transaction failure rolls back managed entities and the receipt together');
check(upload(png(0x456789))->getStatusCode() === 200, 'An explicitly retried rolled-back import can succeed');
\Drupal::currentUser()->setAccount($users['limited']);
$before = counts();
check(upload(png(0x765432))->getStatusCode() === 409 && counts() === $before, 'Missing current media access does not leave a committed import');
\Drupal::currentUser()->setAccount($users['alice']);
check(\Drupal::service('access_manager')->checkNamedRoute('xinshi_ai.figma.assets', ['digest' => hash('sha256', $binary), 'import_id' => '11111111-1111-4111-8111-111111111111'], $users['alice']),
  'The production route admits a logged-in account');
$route = \Drupal::service('router.route_provider')->getRouteByName('xinshi_ai.figma.assets');
check($route->getMethods() === ['PUT'] && $route->getOption('_auth') === ['oauth2'], 'The production route remains OAuth-only and uses PUT');
require __DIR__ . '/http.php';
if (getenv('XINSHI_CHAT_TEST_MYSQL') === '1') {
  require __DIR__ . '/concurrency.php';
}
echo 'Figma asset integration checks: ' . $checks . PHP_EOL;
$complete = TRUE;
