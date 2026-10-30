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
$fixture = '/tmp/credits-test';
if (getenv('XINSHI_CREDITS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  $root !== '/app' || !is_file($fixture . '/installed')) throw new RuntimeException('Use the isolated runner.');
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
if (!in_array(\Drupal::database()->getConnectionOptions()['database'], [$fixture . '/site.sqlite', 'credits_test'], TRUE)) throw new RuntimeException('Unexpected database.');
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
  if (str_starts_with($path, '/api/v3/credits')) {
    check(str_contains($cache, 'no-store') && str_contains($cache, 'private'), 'Private cache policy: ' . $status);
  }
  return ['status' => $status, 'body' => $content, 'data' => json_decode($content, TRUE)];
}
function status(array $response, int $expected, string $label): array {
  check($response['status'] === $expected, $label . ' (HTTP ' . $response['status'] . ')');
  return $response['data'] ?? [];
}
\Drupal::service('module_installer')->install(['simple_oauth']);
Role::create(['id' => 'credit_none', 'label' => 'Limited scope', 'permissions' => ['access content']])->save();
foreach (['credit_reader', 'credit_none'] as $role) {
  Oauth2Scope::create(['name' => $role, 'granularity_id' => 'role',
    'granularity_configuration' => ['role' => $role],
    'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
}
Consumer::create(['label' => 'Credits fixture', 'client_id' => 'credits-http'])->save();
$alice = user_load_by_name('alice');
$alice->addRole('credit_none')->save();
$blocked = User::create(['name' => 'blocked', 'status' => 0, 'roles' => ['credit_reader']]);
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
  $client = \Drupal::service('simple_oauth.repositories.client')->getClientEntity('credits-http');
  $scopeEntity = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier($scope);
  $token = $repo->getNewToken($client, [$scopeEntity], $uid);
  $token->setIdentifier(bin2hex(random_bytes(24)));
  $token->setExpiryDateTime(new DateTimeImmutable($expiry));
  $token->setPrivateKey(new CryptKey('/tmp/credits-test/private.pem'));
  $repo->persistNewAccessToken($token);
  if ($revoked) $repo->revokeAccessToken($token->getIdentifier());
  return $token->convertToJWT()->toString();
}
$bearer = token((string) $alice->id(), 'credit_reader');
$limited = token((string) $alice->id(), 'credit_none');
$invalid = ['invalid-jwt', token((string) $blocked->id(), 'credit_reader'),
  token((string) $alice->id(), 'credit_reader', '-1 hour'),
  token((string) $alice->id(), 'credit_reader', '+1 hour', TRUE)];
\Drupal::service('router.builder')->rebuild();
$server = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql', '-S', '127.0.0.1:8080', __FILE__],
  [0 => ['file', '/dev/null', 'r'], 1 => ['file', $fixture . '/http.log', 'a'],
    2 => ['file', $fixture . '/http.log', 'a']], $pipes);
try {
  for ($i = 0; $i < 60; $i++) {
    if ($socket = @fsockopen('127.0.0.1', 8080, $errno, $error, 0.1)) { fclose($socket); break; }
    usleep(50000);
  }
  $jars = [];
  foreach (['alice', 'bob', 'no_grant'] as $name) {
    $jars[$name] = $fixture . '/' . $name . '.cookie';
    status(http('POST', '/user/login?_format=json', $jars[$name],
      json_encode(['name' => $name, 'pass' => 'credits-test-password']), ['Content-Type: application/json']), 200, 'Cookie login ' . $name);
  }
  $base = '/api/v3/credits/me/account';
  status(http('GET', $base), 403, 'Anonymous balance denied');
  $balance = status(http('GET', $base, $jars['alice']), 200, 'Cookie own balance');
  check($balance['available_atoms'] === '4000', 'Cookie receives exact own balance');
  check(status(http('GET', $base, $jars['bob']), 200, 'Other user balance')['status'] === 'not_initialized', 'No cross-owner amount');
  status(http('GET', $base, $jars['no_grant']), 403, 'Authenticated role without permission denied');
  foreach ($invalid as $value) status(http('GET', $base, $jars['alice'], headers: ['Authorization: Bearer ' . $value]), 401, 'Invalid OAuth cannot borrow Cookie');
  status(http('GET', $base, $jars['alice'], headers: ['Authorization: Bearer ' . $limited]), 403, 'Limited OAuth cannot borrow Cookie permission');
  check(status(http('GET', $base, headers: ['Authorization: Bearer ' . $bearer]), 200, 'OAuth own balance')['available_atoms'] === '4000', 'Scoped token reads own account');
  status(http('GET', $base . '?user=1', $jars['alice']), 422, 'Reject account override');
  status(http('POST', $base, $jars['alice']), 405, 'No implicit balance write');
  status(http('POST', '/api/v3/credits/admin/grants', $jars['alice']), 404, 'Grant HTTP endpoint is not installed');
  $filter = json_decode(file_get_contents($fixture . '/fixtures.json'), TRUE)['filter'];
  $history = '/api/v3/credits/me/transactions?';
  $page = status(http('GET', $history . http_build_query($filter), $jars['alice']), 200, 'Owner history');
  check(count($page['rows']) === 1 && $page['next_cursor'] !== NULL, 'HTTP history pages at requested limit');
  status(http('GET', $history . http_build_query($filter + ['cursor' => $page['next_cursor']]), $jars['bob']), 422, 'Cursor cannot cross users');
  status(http('GET', $history . 'from[]=bad&to=bad', $jars['alice']), 422, 'Malformed query is sanitized');
  \Drupal::configFactory()->getEditable('xinshi_credits.settings')->set('site_id', NULL)->save();
  $unavailable = status(http('GET', $base, $jars['alice']), 503, 'Missing configuration is unavailable');
  check($unavailable === ['code' => 'credits_not_configured'], 'No guessed zero or exception details');
  \Drupal::configFactory()->getEditable('xinshi_credits.settings')->set('site_id', 'fixture')->save();
  echo 'HTTP checks: ' . $checks . PHP_EOL;
}
finally {
  proc_terminate($server);
  proc_close($server);
}
