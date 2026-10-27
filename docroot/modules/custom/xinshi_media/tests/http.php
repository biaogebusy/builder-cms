<?php

declare(strict_types=1);

use Drupal\Core\DrupalKernel;
use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use League\OAuth2\Server\CryptKey;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 5);
$fixture = '/tmp/media-test';
if (getenv('XINSHI_MEDIA_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  $root !== '/app' || !is_file($fixture . '/site.sqlite')) throw new RuntimeException('Use the isolated runner.');
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
if (PHP_SAPI === 'cli-server') {
  $path = realpath('.' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
  if ($path && str_starts_with($path, $root . '/docroot/sites/default/files/') && is_file($path)) return FALSE;
  $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
  $request = Request::createFromGlobals();
  $kernel = DrupalKernel::createFromRequest($request, $loader, 'prod');
  $response = $kernel->handle($request);
  $response->send();
  $kernel->terminate($request, $response);
  return;
}
$request = Request::create('http://127.0.0.1:8080/');
$kernel = DrupalKernel::createFromRequest($request, $loader, 'prod', FALSE);
$kernel->boot();
$kernel->preHandle($request);
if (\Drupal::database()->getConnectionOptions()['database'] !== $fixture . '/site.sqlite') throw new RuntimeException('Unexpected database.');
$checks = 0;
function check(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function http(string $method, string $path, ?string $jar = NULL, string $body = '', array $headers = []): array {
  $curl = curl_init('http://127.0.0.1:8080' . $path);
  $cache = '';
  curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HTTPHEADER => ['Accept: application/json', ...$headers],
    CURLOPT_FOLLOWLOCATION => FALSE, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 30,
    CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$cache): int {
      if (str_starts_with(strtolower($line), 'cache-control:')) $cache = strtolower($line);
      return strlen($line);
    }]);
  if ($method !== 'GET') curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
  if ($jar) {
    curl_setopt($curl, CURLOPT_COOKIEFILE, $jar);
    curl_setopt($curl, CURLOPT_COOKIEJAR, $jar);
  }
  $content = curl_exec($curl);
  $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
  curl_close($curl);
  if ($content === FALSE) throw new RuntimeException('Loopback HTTP failed');
  if (str_starts_with($path, '/api/v3/media')) {
    check(str_contains($cache, 'no-store') && str_contains($cache, 'private'), 'Private cache policy: ' . $status);
  }
  return ['status' => $status, 'body' => $content, 'data' => json_decode($content, TRUE)];
}
function status(array $response, int $expected, string $label): array {
  check($response['status'] === $expected, $label . ' (HTTP ' . $response['status'] . ')');
  return $response['data'] ?? [];
}
\Drupal::service('module_installer')->install(['simple_oauth']);
Role::create(['id' => 'media_none', 'label' => 'Limited scope', 'permissions' => ['access content']])->save();
foreach (['media_author', 'media_reader', 'media_none'] as $role) {
  Oauth2Scope::create(['name' => $role, 'granularity_id' => 'role',
    'granularity_configuration' => ['role' => $role],
    'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
}
Consumer::create(['label' => 'Media fixture', 'client_id' => 'media-http'])->save();
$alice = user_load_by_name('alice');
$alice->addRole('media_none')->save();
$blocked = User::create(['name' => 'blocked', 'status' => 0, 'roles' => ['media_author']]);
$blocked->save();
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $private);
file_put_contents($fixture . '/private.pem', $private);
file_put_contents($fixture . '/public.pem', openssl_pkey_get_details($key)['key']);
chmod($fixture . '/private.pem', 0600);
\Drupal::configFactory()->getEditable('simple_oauth.settings')->set('private_key', $fixture . '/private.pem')
  ->set('public_key', $fixture . '/public.pem')->save();
function token(string $uid, string $scope, string $expiry = '+1 hour', bool $revoked = FALSE): string {
  $repo = \Drupal::service('simple_oauth.repositories.access_token');
  $client = \Drupal::service('simple_oauth.repositories.client')->getClientEntity('media-http');
  $scopeEntity = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier($scope);
  $token = $repo->getNewToken($client, [$scopeEntity], $uid);
  $token->setIdentifier(bin2hex(random_bytes(24)));
  $token->setExpiryDateTime(new DateTimeImmutable($expiry));
  $token->setPrivateKey(new CryptKey('/tmp/media-test/private.pem'));
  $repo->persistNewAccessToken($token);
  if ($revoked) $repo->revokeAccessToken($token->getIdentifier());
  return $token->convertToJWT()->toString();
}
$bearer = token((string) $alice->id(), 'media_author');
$limited = token((string) $alice->id(), 'media_none');
$invalid = ['invalid-jwt', token((string) $blocked->id(), 'media_author'),
  token((string) $alice->id(), 'media_author', '-1 hour'), token((string) $alice->id(), 'media_author', '+1 hour', TRUE)];
\Drupal\node\Entity\NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
\Drupal\field\Entity\FieldStorageConfig::create(['field_name' => 'field_image', 'entity_type' => 'node',
  'type' => 'entity_reference', 'settings' => ['target_type' => 'media']])->save();
\Drupal\field\Entity\FieldConfig::create(['field_name' => 'field_image', 'entity_type' => 'node',
  'bundle' => 'article', 'label' => 'Image'])->save();
$fixtures = json_decode(file_get_contents($fixture . '/fixtures.json'), TRUE);
$node = \Drupal\node\Entity\Node::create(['type' => 'article', 'title' => 'Historical reference',
  'uid' => $alice->id(), 'status' => 1, 'field_image' => ['target_id' => $fixtures['image']['id']]]);
$node->save();
\Drupal::service('router.builder')->rebuild();
$server = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-S', '127.0.0.1:8080', __FILE__],
  [0 => ['file', '/dev/null', 'r'], 1 => ['file', $fixture . '/http.log', 'a'],
    2 => ['file', $fixture . '/http.log', 'a']], $pipes);
try {
  for ($i = 0; $i < 60; $i++) {
    if ($socket = @fsockopen('127.0.0.1', 8080, $errno, $error, 0.1)) { fclose($socket); break; }
    usleep(50000);
  }
  $jars = []; $csrf = [];
  foreach (['alice', 'bob', 'reader'] as $name) {
    $jars[$name] = $fixture . '/' . $name . '.cookie';
    $login = status(http('POST', '/user/login?_format=json', $jars[$name],
      json_encode(['name' => $name, 'pass' => 'media-test-password']), ['Content-Type: application/json']), 200, 'Cookie login ' . $name);
    $csrf[$name] = $login['csrf_token'];
  }
  $a = $jars['alice']; $b = $jars['bob']; $r = $jars['reader'];
  $base = '/api/v3/media';
  status(http('GET', $base), 403, 'Anonymous catalog denied');
  foreach ($invalid as $value) status(http('GET', $base, $a, headers: ['Authorization: Bearer ' . $value]), 401, 'Invalid Bearer cannot borrow Cookie');
  $limitedCaps = status(http('GET', $base . '/capabilities', $a, headers: ['Authorization: Bearer ' . $limited]), 200, 'Limited OAuth capabilities');
  check(!array_filter($limitedCaps['types'], fn($type) => $type['canUpload']), 'Limited OAuth cannot borrow Cookie upload grants');
  check(count(status(http('GET', $base, headers: ['Authorization: Bearer ' . $bearer]), 200, 'OAuth own collection')['rows']) === 2, 'OAuth owner scope');
  check(count(status(http('GET', $base, $b), 200, 'Bob own collection')['rows']) === 1, 'Cookie owner scope');
  check(count(status(http('GET', $base, $r), 200, 'Read-all collection')['rows']) === 3, 'Read-all scope includes visible records');
  status(http('GET', $base . '?scope=all', $b), 403, 'Explicit all scope denied');
  status(http('GET', $base . '?name[]=x', $a), 400, 'Malformed search rejected');
  $imagePath = $base . '/' . $fixtures['image']['uuid'];
  $privatePath = $base . '/' . $fixtures['document']['uuid'];
  status(http('GET', $imagePath, $b), 404, 'Cross-owner details denied');
  status(http('GET', $privatePath . '/file', $b), 404, 'Cross-owner private file denied');
  $download = http('GET', $privatePath . '/file', $a);
  check($download['status'] === 200 && $download['body'] === 'Private document', 'Owner receives original private bytes');
  $display = status(http('GET', $imagePath . '/display'), 200, 'Anonymous historical UUID still displays');
  check(array_keys($display) === ['src', 'alt'], 'Public projection exposes no management metadata');
  check(http('GET', parse_url($display['src'], PHP_URL_PATH))['status'] === 200, 'Historical original URL still serves after removal');
  status(http('GET', $privatePath . '/display'), 404, 'Private source has no public projection');
  $relation = status(http('GET', '/jsonapi/node/article/' . $node->uuid() . '?include=field_image.field_media_image'), 200, 'Published node relationship remains readable');
  check(count($relation['included'] ?? []) === 2, 'Historical node includes its public media and file');
  $jsonapi = '/jsonapi/media/image/' . $fixtures['image']['uuid'];
  status(http('GET', $jsonapi . '?include=field_media_image', $b), 403, 'Direct JSON:API detail cannot bypass owner scope');
  $collection = status(http('GET', '/jsonapi/media/image', $b), 200, 'Direct JSON:API collection');
  check(count($collection['data']) === 1 && $collection['data'][0]['id'] === $fixtures['bob']['uuid'], 'JSON:API pagination is scoped in SQL');
  status(http('PATCH', $imagePath, $a, '{"name":"new"}', ['Content-Type: application/json']), 403, 'Cookie write needs CSRF');
  status(http('PATCH', $imagePath, $a, '{"name":"new"}', ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf['alice']]), 200, 'Cookie rename with CSRF');
  status(http('PATCH', $imagePath, $r, '{"name":"new"}', ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf['reader']]), 403, 'Read-all does not grant rename');
  $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="8" height="8"/></svg>';
  $uploadHeaders = ['Content-Type: application/octet-stream', 'Content-Disposition: file; filename="logo.svg"', 'Authorization: Bearer ' . $bearer];
  $vector = status(http('POST', $base . '/vector/upload', body: $svg, headers: $uploadHeaders), 201, 'OAuth SVG upload');
  check($vector['uuid'] !== $vector['fileId'], 'HTTP upload returns distinct media/file UUIDs');
  $binary = http('GET', $base . '/' . $vector['uuid'] . '/file', $a);
  check($binary['status'] === 200 && !str_contains($binary['body'], 'script'), 'Sanitized SVG is served');
  $native = status(http('POST', '/jsonapi/media/vector/field_media_vector', body: $svg, headers: $uploadHeaders), 201, 'Native JSON:API SVG upload');
  $nativeBytes = http('GET', parse_url($native['data']['attributes']['uri']['url'], PHP_URL_PATH));
  check($nativeBytes['status'] === 200 && !str_contains($nativeBytes['body'], 'script'), 'Native upload sanitized before public storage');
  status(http('DELETE', '/jsonapi/file/file/' . $fixtures['image']['fileId'], $a,
    headers: ['X-CSRF-Token: ' . $csrf['alice']]), 403, 'Physical file deletion denied despite own-file permission');
  status(http('DELETE', $jsonapi, $a, headers: ['X-CSRF-Token: ' . $csrf['alice']]), 403, 'Physical metadata deletion denied');
  status(http('DELETE', $base . '/' . $vector['uuid'], $a, headers: ['X-CSRF-Token: ' . $csrf['alice']]), 204, 'Remove from library');
  status(http('GET', $base . '/' . $vector['uuid'] . '/display'), 200, 'Removed SVG retains public display');
  echo 'Media HTTP checks: ' . $checks . PHP_EOL;
} catch (Throwable $error) {
  echo file_get_contents($fixture . '/http.log');
  throw $error;
} finally {
  proc_terminate($server);
  proc_close($server);
}
