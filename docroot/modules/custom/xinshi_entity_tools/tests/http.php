<?php

declare(strict_types=1);

/**
 * @file
 * Real Cookie, CSRF, OAuth scope and Form API checks in the disposable site.
 */

use Drupal\consumers\Entity\Consumer;
use Drupal\Core\DrupalKernel;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use League\OAuth2\Server\CryptKey;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 5);
$fixture = '/tmp/entity-tools-test';
if (getenv('XINSHI_ENTITY_TOOLS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  $root !== '/app' || !is_file($fixture . '/site.sqlite')) {
  throw new RuntimeException('Use the disposable entity tool runner only.');
}
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
if (PHP_SAPI === 'cli-server') {
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
if (\Drupal::database()->getConnectionOptions()['database'] !== $fixture . '/site.sqlite') {
  throw new RuntimeException('Unexpected database.');
}
$checks = 0;
function check(bool $ok, string $message): void {
  if (!$ok) {
    throw new RuntimeException($message);
  }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function canonical(array $value): array {
  if (!array_is_list($value)) {
    ksort($value);
  }
  foreach ($value as &$item) {
    if (is_array($item)) {
      $item = canonical($item);
    }
  }
  return $value;
}
function http(string $method, string $path, ?string $bearer = NULL, ?string $jar = NULL,
  string $body = '', string $type = 'application/json', string $csrf = ''): array {
  $curl = curl_init('http://127.0.0.1:8080' . $path);
  $headers = ['Accept: ' . ($type === 'application/x-www-form-urlencoded' ? 'text/html' : 'application/json'),
    'Content-Type: ' . $type];
  if ($bearer !== NULL) {
    $headers[] = 'Authorization: Bearer ' . $bearer;
  }
  if ($csrf !== '') {
    $headers[] = 'X-CSRF-Token: ' . $csrf;
  }
  $cache = '';
  curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_FOLLOWLOCATION => FALSE, CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT => 30, CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$cache): int {
      if (str_starts_with(strtolower($line), 'cache-control:')) {
        $cache = strtolower($line);
      }
      return strlen($line);
    }]);
  if ($method !== 'GET') {
    curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
  }
  if ($jar !== NULL) {
    curl_setopt($curl, CURLOPT_COOKIEFILE, $jar);
    curl_setopt($curl, CURLOPT_COOKIEJAR, $jar);
  }
  $content = curl_exec($curl);
  $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
  curl_close($curl);
  check(is_string($content), 'Loopback transport completes');
  if (str_starts_with($path, '/api/v3/entity-tools/')) {
    check(str_contains($cache, 'no-store') && str_contains($cache, 'private'), 'API response is private/no-store: ' . $status);
  }
  return ['status' => $status, 'data' => json_decode($content, TRUE), 'body' => $content];
}
function status(array $response, int $expected, string $label): array {
  check($response['status'] === $expected, $label . ' (HTTP ' . $response['status'] . ', expected ' . $expected . ')');
  if ($expected >= 400) {
    check(!array_diff(array_keys($response['data'] ?? []), ['code', 'executionId', 'outcome']),
      'API error contains no exception or request detail');
  }
  return $response['data'] ?? [];
}
function form(string $jar, string $path = '/admin/config/services/entity-tools/add'): array {
  $response = http('GET', $path, jar: $jar, type: 'application/x-www-form-urlencoded');
  check($response['status'] === 200, 'Administrator can open the actual resource form');
  $document = new DOMDocument();
  @$document->loadHTML($response['body']);
  $xpath = new DOMXPath($document);
  $hidden = [];
  foreach (['form_id', 'form_build_id', 'form_token'] as $key) {
    $element = $xpath->query('//input[@name="' . $key . '"]')->item(0);
    check($element instanceof DOMElement, 'Form provides ' . $key);
    $hidden[$key] = $element->getAttribute('value');
  }
  return [$hidden, $xpath];
}

\Drupal::service('module_installer')->install(['simple_oauth']);
$grants = [
  'full' => ['access content', 'view own unpublished content', 'use entity tools', 'write entity tools',
    'create article content', 'edit own article content'],
  'read' => ['access content', 'view own unpublished content', 'use entity tools'],
  'none' => ['access content'],
];
foreach ($grants as $name => $permissions) {
  $id = 'entity_tools_http_' . $name;
  Role::create(['id' => $id, 'label' => $id, 'permissions' => $permissions])->save();
  Oauth2Scope::create(['name' => $id, 'granularity_id' => 'role',
    'granularity_configuration' => ['role' => $id],
    'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
}
Consumer::create(['label' => 'Entity tools HTTP', 'client_id' => 'entity-tools-http'])->save();
$password = bin2hex(random_bytes(24));
$user = User::create(['name' => 'entity-tools-http', 'pass' => $password, 'status' => 1,
  'roles' => array_map(fn($name) => 'entity_tools_http_' . $name, array_keys($grants))]);
$user->save();
$other = User::create(['name' => 'entity-tools-http-other', 'status' => 1, 'roles' => ['entity_tools_http_full']]);
$other->save();
$blocked = User::create(['name' => 'entity-tools-http-blocked', 'status' => 0, 'roles' => ['entity_tools_http_full']]);
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
  $client = \Drupal::service('simple_oauth.repositories.client')->getClientEntity('entity-tools-http');
  $scopeEntity = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier('entity_tools_http_' . $scope);
  $token = $repo->getNewToken($client, [$scopeEntity], $uid);
  $token->setIdentifier(bin2hex(random_bytes(24)));
  $token->setExpiryDateTime(new DateTimeImmutable($expiry));
  $token->setPrivateKey(new CryptKey('/tmp/entity-tools-test/private.pem'));
  $repo->persistNewAccessToken($token);
  if ($revoked) {
    $repo->revokeAccessToken($token->getIdentifier());
  }
  return $token->convertToJWT()->toString();
}
$tokens = [];
foreach (array_keys($grants) as $scope) {
  $tokens[$scope] = token((string) $user->id(), $scope);
}
$tokens['other'] = token((string) $other->id(), 'full');
$tokens['blocked'] = token((string) $blocked->id(), 'full');
$tokens['expired'] = token((string) $user->id(), 'full', '-1 hour');
$tokens['revoked'] = token((string) $user->id(), 'full', '+1 hour', TRUE);
Role::create(['id' => 'entity_resource_admin', 'label' => 'Resource administrator',
  'permissions' => ['access administration pages', 'administer entity tool resources']])->save();
$admin = User::create(['name' => 'entity-resource-admin', 'pass' => $password, 'status' => 1,
  'roles' => ['entity_resource_admin']]);
$admin->save();
\Drupal::service('router.builder')->rebuild();
$server = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-S', '127.0.0.1:8080', __FILE__],
  [0 => ['file', '/dev/null', 'r'], 1 => ['file', $fixture . '/http.log', 'a'],
    2 => ['file', $fixture . '/http.log', 'a']], $pipes);
try {
  $ready = FALSE;
  for ($i = 0; $i < 60; $i++) {
    if ($socket = @fsockopen('127.0.0.1', 8080, $errno, $error, 0.1)) {
      fclose($socket);
      $ready = TRUE;
      break;
    }
    usleep(50000);
  }
  check($ready, 'Loopback HTTP server ready');
  $base = '/api/v3/entity-tools';
  $jar = $fixture . '/cookie.txt';
  $login = status(http('POST', '/user/login?_format=json', jar: $jar,
    body: json_encode(['name' => $user->getAccountName(), 'pass' => $password])), 200, 'Cookie login');
  $scope = ['resourceId' => 'articles', 'resourceVersion' => 7, 'language' => 'en'];
  $input = $scope + ['operation' => 'create', 'values' => ['title' => 'HTTP article', 'body' => ['value' => 'HTTP body']]];
  $payload = json_encode($input, JSON_THROW_ON_ERROR);
  foreach ([$base . '/capabilities' => 'GET', $base . '/resources' => 'GET', $base . '/validate' => 'POST'] as $path => $method) {
    status(http($method, $path, body: $payload), 403, 'Anonymous denied');
    foreach (['invalid', 'blocked', 'expired', 'revoked'] as $bad) {
      status(http($method, $path, $tokens[$bad] ?? 'invalid-jwt', $jar, $payload), 401, 'Invalid Bearer cannot borrow Cookie');
    }
  }
  foreach (['full', 'read', 'none', 'full', 'read'] as $role) {
    $capabilities = status(http('GET', $base . '/capabilities', $tokens[$role]), 200, 'Effective OAuth capability');
    check($capabilities['uid'] === (string) $user->id() && $capabilities['permissions'] ===
      ($role === 'full' ? ['entities.read', 'entities.write'] : ($role === 'read' ? ['entities.read'] : [])),
      'Full account roles cannot widen a scoped token');
  }
  status(http('GET', $base . '/resources', $tokens['none'], $jar), 403, 'Limited Bearer cannot borrow Cookie permissions');
  check(count(status(http('GET', $base . '/resources', $tokens['read']), 200, 'Read-only resource discovery')['resources']) === 2,
    'Only configured resources are visible');
  check(status(http('POST', $base . '/validate', $tokens['full'], body: $payload), 200, 'Actual HTTP validation')['valid'],
    'Validation returns a usable field result');
  $execution = \Drupal::service('uuid')->generate();
  $write = $base . '/operations/' . $execution;
  status(http('POST', $write, jar: $jar, body: $payload), 403, 'Cookie writes require CSRF');
  status(http('POST', $write, jar: $jar, body: $payload, csrf: 'wrong'), 403, 'Invalid CSRF is rejected');
  status(http('POST', $write, $tokens['read'], $jar, $payload, csrf: $login['csrf_token']), 403,
    'CSRF does not elevate a read-only OAuth scope');
  $created = status(http('POST', $write, $tokens['full'], body: $payload), 200, 'Bearer-only write commits draft');
  check($created['input'] === $input && $created['result']['status'] === 'draft', 'Receipt binds exact approved input');
  check(canonical(status(http('POST', $write, $tokens['full'], body: $payload), 200,
    'Repeated write returns receipt')) === canonical($created),
    'Duplicate HTTP execution is idempotent');
  check(canonical(status(http('GET', $write, $tokens['read']), 200,
    'Read-only actor reconciles own write')) === canonical($created),
    'Reconciliation does not require current write scope');
  status(http('GET', $write, $tokens['other']), 403, 'Other account cannot read the receipt');
  $csrf = trim(http('GET', '/session/token', jar: $jar)['body']);
  status(http('POST', $base . '/operations/' . \Drupal::service('uuid')->generate(), jar: $jar,
    body: $payload, csrf: $csrf), 200, 'Valid Cookie CSRF write succeeds');
  $read = $scope + ['entityId' => $created['result']['id'], 'fields' => ['title', 'body']];
  $entity = status(http('POST', $base . '/read', $tokens['read'], body: json_encode($read)), 200, 'Scoped detail read');
  check($entity['entity']['values']['body']['value'] === 'HTTP body', 'Details contain the selected field values');
  status(http('POST', $base . '/read', $tokens['other'], body: json_encode($read)), 404, 'Other author cannot read the draft');
  $query = $scope + ['fields' => ['title'], 'filters' => [], 'limit' => 1];
  $page = status(http('POST', $base . '/query', jar: $jar, body: json_encode($query)), 200,
    'Read-only query POST does not require CSRF');
  check(count($page['items']) === 1 && $page['nextAfter'] !== NULL, 'Bounded query advertises continuation');
  $next = status(http('POST', $base . '/query', $tokens['read'], body: json_encode($query + ['after' => $page['nextAfter']])),
    200, 'Next query page');
  check($next['items'][0]['id'] !== $page['items'][0]['id'], 'Pagination advances without repeating an entity');
  foreach (['{', 'null', json_encode($query + ['uid' => '1']), json_encode(array_replace($query, ['after' => NULL])),
    json_encode(array_replace($query, ['resourceVersion' => '7']))] as $invalid) {
    status(http('POST', $base . '/query', $tokens['full'], body: $invalid), 400, 'Malformed query rejected');
  }
  $update = $scope + ['operation' => 'update', 'entityId' => $created['result']['id'],
    'expectedVersion' => $created['result']['version'], 'values' => ['title' => 'Updated HTTP article']];
  status(http('POST', $base . '/operations/' . \Drupal::service('uuid')->generate(), $tokens['full'], body: json_encode($update)),
    200, 'Actual HTTP update creates a revision');
  $refused = status(http('POST', $base . '/operations/' . \Drupal::service('uuid')->generate(), $tokens['full'], body: json_encode($update)),
    409, 'Stale HTTP update fails');
  check($refused['outcome'] === 'not_written', 'A version refusal is a definitive no-write outcome');
  $adminJar = $fixture . '/admin-cookie.txt';
  status(http('POST', '/user/login?_format=json', jar: $adminJar,
    body: json_encode(['name' => $admin->getAccountName(), 'pass' => $password])), 200, 'Resource administrator login');
  check(status(http('GET', $base . '/capabilities', jar: $adminJar), 200, 'Administrator capabilities')['permissions'] === [],
    'Configuration administration grants no content tool capability');
  check(http('GET', '/admin/config/services/entity-tools/add', jar: $jar,
    type: 'application/x-www-form-urlencoded')['status'] === 403, 'Content author cannot configure resources');
  [$hidden, $xpath] = form($adminJar);
  check($xpath->query('//input[@name="status" and @checked]')->length === 0, 'New resources start disabled in the form');
  $values = ['id' => 'http_articles', 'label' => 'HTTP articles', 'bundle' => 'article',
    'fields' => ['title' => 'title'], 'languages' => ['en' => 'en'], 'text_format' => 'plain_text',
    'status' => 1, 'op' => 'Save'];
  http('POST', '/admin/config/services/entity-tools/add', jar: $adminJar,
    body: http_build_query(array_replace($hidden + $values, ['form_token' => 'invalid'])),
    type: 'application/x-www-form-urlencoded');
  check(!\Drupal::service('config.storage')->exists('xinshi_entity_tools.resource.http_articles'),
    'Invalid form CSRF cannot create a resource');
  [$hidden] = form($adminJar);
  $response = http('POST', '/admin/config/services/entity-tools/add', jar: $adminJar,
    body: http_build_query($hidden + $values), type: 'application/x-www-form-urlencoded');
  check($response['status'] === 303, 'Valid resource form redirects after save (HTTP ' . $response['status'] . ')');
  check(\Drupal::service('config.storage')->exists('xinshi_entity_tools.resource.http_articles'),
    'Valid Form API submission saves a resource');
  $editPath = '/admin/config/services/entity-tools/http_articles';
  [$hidden] = form($adminJar, $editPath);
  $editValues = array_replace($values, ['label' => 'Retired HTTP articles']);
  unset($editValues['status']);
  $response = http('POST', $editPath, jar: $adminJar,
    body: http_build_query($hidden + $editValues),
    type: 'application/x-www-form-urlencoded');
  check($response['status'] === 303, 'Edited resource form redirects after save (HTTP ' . $response['status'] . ')');
  $edited = \Drupal::entityTypeManager()->getStorage('entity_tool_resource')->loadUnchanged('http_articles');
  check($edited->get('version') === 2 && !$edited->status(),
    'Editing and disabling a resource advances its version: ' . json_encode([
      'version' => $edited->get('version'), 'status' => $edited->status()]));
  status(http('GET', $base . '/resources/http_articles', $tokens['full']), 404, 'Disabled form configuration is not discoverable');
  Role::load('entity_tools_http_full')->revokePermission('write entity tools')->save();
  status(http('POST', $base . '/operations/' . \Drupal::service('uuid')->generate(), $tokens['full'], body: $payload),
    403, 'A new HTTP request observes scope permission withdrawal');
  check(canonical(status(http('GET', $write, $tokens['full']), 200,
    'Receipt remains readable after write withdrawal')) === canonical($created),
    'Historical result is immutable after later revisions');
  echo 'Entity tool HTTP: ' . $checks . ' checks passed.' . PHP_EOL;
}
catch (Throwable $error) {
  fwrite(STDERR, substr((string) file_get_contents($fixture . '/http.log'), -6000));
  throw $error;
}
finally {
  proc_terminate($server);
  proc_close($server);
}
