<?php

declare(strict_types=1);

/**
 * @file
 * Real Cookie and signed OAuth requests against the disposable integration site.
 */

use Drupal\consumers\Entity\Consumer;
use Drupal\Core\DrupalKernel;
use Drupal\node\Entity\Node;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use League\OAuth2\Server\CryptKey;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 5);
$fixture = '/tmp/analytics-test';
if (getenv('XINSHI_ANALYTICS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv')
  || $root !== '/app' || !is_file($fixture . '/site.sqlite')) {
  throw new RuntimeException('Use the disposable analytics runner only.');
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
if (($argv[1] ?? '') === 'withdraw') {
  Role::load('analytics_http_full')->revokePermission('query analytics dataset editorial_activity')->save();
  exit;
}
$checks = 0;
function check(bool $ok, string $message): void {
  if (!$ok) {
    throw new RuntimeException($message);
  }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function http(string $method, string $path, ?string $bearer = NULL, ?string $jar = NULL,
  string $body = '', string $type = 'application/json'): array {
  $curl = curl_init('http://127.0.0.1:8080' . $path);
  $headers = ['Accept: application/json', 'Content-Type: ' . $type];
  if ($bearer !== NULL) {
    $headers[] = 'Authorization: Bearer ' . $bearer;
  }
  $cache = '';
  curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_FOLLOWLOCATION => FALSE, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 30,
    CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$cache): int {
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
  if ($content === FALSE) {
    throw new RuntimeException('Loopback HTTP transport failed.');
  }
  if (str_starts_with($path, '/api/v3/analytics/')) {
    check(str_contains($cache, 'no-store') && str_contains($cache, 'private'),
      'Private no-store HTTP ' . $status . ' ' . $path);
  }
  return ['status' => $status, 'data' => json_decode($content, TRUE), 'body' => $content];
}
function status(array $response, int $expected, string $label): array {
  check($response['status'] === $expected, $label . ' (HTTP ' . $response['status'] . ', expected ' . $expected . ')');
  if ($expected >= 400) {
    check(array_keys($response['data'] ?? []) === ['code'], 'Failure exposes only a stable code');
  }
  return $response['data'];
}

\Drupal::service('module_installer')->install(['simple_oauth']);
$grants = [
  'full' => ['access content', 'query analytics datasets', 'query analytics dataset editorial_activity', 'view analytics fixture fields'],
  'query' => ['access content', 'query analytics datasets', 'query analytics dataset editorial_activity'],
  'general' => ['access content', 'query analytics datasets'],
  'scoped' => ['access content', 'query analytics datasets', 'view analytics fixture fields'],
  'none' => ['access content'],
];
foreach ($grants as $name => $permissions) {
  $id = 'analytics_http_' . $name;
  Role::create(['id' => $id, 'label' => $id, 'permissions' => $permissions])->save();
  Oauth2Scope::create(['name' => $id, 'granularity_id' => 'role',
    'granularity_configuration' => ['role' => $id],
    'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
}
Consumer::create(['label' => 'Analytics HTTP test', 'client_id' => 'analytics-http'])->save();
$password = bin2hex(random_bytes(24));
$user = User::create(['name' => 'analytics-http', 'pass' => $password, 'status' => 1,
  'roles' => array_map(fn($name) => 'analytics_http_' . $name, array_keys($grants))]);
$user->save();
$blocked = User::create(['name' => 'analytics-http-blocked', 'status' => 0, 'roles' => ['analytics_http_full']]);
$blocked->save();
foreach ([$user->id(), $user->id(), $blocked->id()] as $owner) {
  Node::create(['type' => 'article', 'title' => 'HTTP synthetic record', 'uid' => $owner,
    'status' => 1, 'langcode' => 'en', 'created' => strtotime('2026-01-10 UTC')])->save();
}
node_access_rebuild();
\Drupal::state()->set('analytics_test.require_field_permission', TRUE);
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $private);
file_put_contents($fixture . '/private.pem', $private);
file_put_contents($fixture . '/public.pem', openssl_pkey_get_details($key)['key']);
chmod($fixture . '/private.pem', 0600);
\Drupal::configFactory()->getEditable('simple_oauth.settings')->set('private_key', $fixture . '/private.pem')
  ->set('public_key', $fixture . '/public.pem')->save();
function token(string $uid, string $scope, string $expiry = '+1 hour', bool $revoked = FALSE): string {
  $repo = \Drupal::service('simple_oauth.repositories.access_token');
  $client = \Drupal::service('simple_oauth.repositories.client')->getClientEntity('analytics-http');
  $scopeEntity = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier('analytics_http_' . $scope);
  $token = $repo->getNewToken($client, [$scopeEntity], $uid);
  $token->setIdentifier(bin2hex(random_bytes(24)));
  $token->setExpiryDateTime(new DateTimeImmutable($expiry));
  $token->setPrivateKey(new CryptKey('/tmp/analytics-test/private.pem'));
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
$tokens['blocked'] = token((string) $blocked->id(), 'full');
$tokens['expired'] = token((string) $user->id(), 'full', '-1 hour');
$tokens['revoked'] = token((string) $user->id(), 'full', '+1 hour', TRUE);
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
  check($ready, 'Loopback server ready');
  $query = ['datasetId' => 'editorial_activity', 'datasetVersion' => 1, 'dimensions' => ['category'],
    'filters' => [], 'scope' => ['kind' => 'range', 'from' => '2026-01-01T00:00:00Z', 'toExclusive' => '2026-02-01T00:00:00Z'],
    'timezone' => 'UTC', 'language' => 'en'];
  $body = json_encode($query, JSON_THROW_ON_ERROR);
  $cap = '/api/v3/analytics/capabilities';
  $datasets = '/api/v3/analytics/datasets?language=en';
  $count = '/api/v3/analytics/count';
  $jar = $fixture . '/cookie.txt';
  check(http('POST', '/user/login?_format=json', jar: $jar,
    body: json_encode(['name' => $user->getAccountName(), 'pass' => $password]))['status'] === 200,
    'Real Cookie login succeeds');
  foreach ([$cap, $datasets, $count] as $path) {
    status(http($path === $count ? 'POST' : 'GET', $path, body: $body), 403, 'Anonymous rejected');
    foreach (['invalid', 'blocked', 'expired', 'revoked'] as $bad) {
      status(http($path === $count ? 'POST' : 'GET', $path, $tokens[$bad] ?? 'invalid-jwt', $jar, $body),
        401, 'Invalid Bearer cannot borrow Cookie: ' . $bad);
    }
  }
  $capabilities = status(http('GET', $cap, $tokens['full']), 200, 'Scoped capabilities');
  check($capabilities === ['uid' => (string) $user->id(), 'permissions' => ['analytics.query']], 'Capabilities bind UID and effective grant');
  check(status(http('GET', $cap, $tokens['none']), 200, 'No query scope')['permissions'] === [], 'Full account roles do not widen a token');
  status(http('POST', $count, $tokens['none'], $jar, $body), 403, 'Cookie cannot widen a valid limited Bearer');
  foreach (['general', 'query', 'full', 'general', 'query', 'full'] as $scope) {
    $found = status(http('GET', $datasets, $tokens[$scope]), 200, 'Scoped discovery: ' . $scope);
    check(count($found['datasets']) === ($scope === 'full' ? 1 : 0), 'Dataset and field grants retain OAuth scope');
    $result = status(http('POST', $count, $tokens[$scope], body: $body), $scope === 'full' ? 200 : 403,
      'Scoped count: ' . $scope);
    if ($scope === 'full') {
      check($result['rows'] === [['dimensions' => ['article'], 'count' => 2]], 'Count retains entity grants');
      file_put_contents($fixture . '/http-protocol.json', json_encode(['discovery' => $found, 'result' => $result], JSON_THROW_ON_ERROR));
    }
  }
  check(status(http('POST', $count, jar: $jar, body: $body), 200, 'Read-only Cookie POST needs no CSRF')['rows'][0]['count'] === 2,
    'Cookie uses current user entity access');
  foreach (['{', 'null', str_replace('"filters":[]', '"filters":{}', $body),
    json_encode($query + ['uid' => '1']), json_encode(array_replace($query, ['datasetVersion' => '1']))] as $invalid) {
    status(http('POST', $count, $tokens['full'], body: $invalid), 400, 'Invalid query rejected');
  }
  status(http('POST', $count, $tokens['full'], body: str_repeat('x', 1024 * 1024 + 1)), 413, 'Body bound enforced');
  status(http('POST', $count, $tokens['full'], body: $body, type: 'text/plain'), 415, 'Content type required');
  status(http('POST', $count, $tokens['full'], body: json_encode(array_replace($query, ['datasetVersion' => 2]))), 409, 'Version failure is distinct');
  status(http('POST', $count, $tokens['full'], body: json_encode(array_replace($query,
    ['scope' => ['kind' => 'range', 'from' => '2025-01-01T00:00:00Z', 'toExclusive' => '2026-02-01T00:00:00Z']]))), 422, 'Range limit is distinct');
  status(http('GET', '/api/v3/analytics/datasets?language[]=en', $tokens['full']), 400, 'Discovery language shape rejected');
  $evidencePath = '/api/v3/analytics/evidence';
  $verifyPath = '/api/v3/analytics/evidence/verify';
  $sealed = status(http('POST', $evidencePath, $tokens['full'], body: $body), 200, 'HTTP evidence capture');
  check($sealed['result']['rows'] === [['dimensions' => ['article'], 'count' => 2]], 'Evidence matches the authenticated count');
  $wire = json_encode($sealed, JSON_THROW_ON_ERROR);
  $verified = status(http('POST', $verifyPath, $tokens['full'], body: $wire), 200, 'HTTP evidence verifies');
  check($verified === ['valid' => TRUE], 'Verification exposes no replacement count or private fingerprint');
  file_put_contents($fixture . '/evidence-protocol.json', json_encode(['evidence' => $sealed, 'verification' => $verified], JSON_THROW_ON_ERROR));
  check(status(http('POST', $verifyPath, jar: $jar, body: $wire), 200, 'Same account Cookie verifies')['valid'],
    'A receipt is not tied to a particular access token');
  foreach ([$evidencePath => $body, $verifyPath => $wire] as $path => $payload) {
    status(http('POST', $path, body: $payload), 403, 'Anonymous cannot use evidence endpoints');
    status(http('POST', $path, $tokens['expired'], $jar, $payload), 401, 'Expired Bearer cannot borrow Cookie for evidence');
    status(http('POST', $path, $tokens['none'], body: $payload), 403, 'No query scope cannot use evidence');
    status(http('POST', $path, $tokens['query'], body: $payload), $path === $verifyPath ? 410 : 403,
      'Field-limited OAuth scope cannot use full evidence');
    status(http('POST', $path, $tokens['full'], body: str_repeat('x', 1024 * 1024 + 1)), 413, 'Evidence body size bounded');
    status(http('POST', $path, $tokens['full'], body: $payload, type: 'text/plain'), 415, 'Evidence JSON content type required');
  }
  $tampered = $sealed;
  $tampered['result']['rows'][0]['count']++;
  check(status(http('POST', $verifyPath, $tokens['full'], body: json_encode($tampered)), 410,
    'Tampered result rejected')['code'] === 'evidence_unavailable', 'Evidence refusal has a distinct stable code');
  $tampered = $sealed;
  $tampered['version'] = 1;
  status(http('POST', $verifyPath, $tokens['full'], body: json_encode($tampered)), 400, 'Unsupported evidence format rejected');
  $tampered = $sealed;
  $tampered['result']['rows'][0]['dimensions'] = new stdClass();
  status(http('POST', $verifyPath, $tokens['full'], body: json_encode($tampered)), 400, 'Evidence preserves wire array types');
  require __DIR__ . '/scoped.http.php';
  require __DIR__ . '/settings.http.php';
  $process = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', __FILE__, 'withdraw'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', $fixture . '/withdraw.log', 'w']], $pipes);
  check(proc_close($process) === 0, 'Withdraw dataset grant in a separate request');
  status(http('POST', $count, $tokens['full'], body: $body), 403, 'Next request observes role permission withdrawal');
  status(http('POST', $verifyPath, $tokens['full'], body: $wire), 410, 'Saved evidence observes permission withdrawal');
  echo 'PASS: ' . $checks . ' real Drupal HTTP checks' . PHP_EOL;
}
finally {
  proc_terminate($server);
  proc_close($server);
}
