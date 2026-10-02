<?php

declare(strict_types=1);

/**
 * @file
 * Exercises Cookie and signed OAuth authentication through real HTTP requests.
 *
 * Run after integration-smoke.php in a disposable, network-none container with
 * no mounts and a source copy at /app. Only loopback HTTP is used. No providers,
 * real credentials, application sites, or external OAuth services are involved.
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
$fixture = '/tmp/xinshi-api-test';
if (getenv('XINSHI_API_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    $root !== '/app' || !is_file($fixture . '/site.sqlite')) {
  throw new RuntimeException('Use the disposable integration-smoke.php site only.');
}
require __DIR__ . '/bootstrap.php';
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');

// The same script acts as the loopback server's router. Every HTTP request gets
// a fresh Drupal kernel, real authentication providers and persisted sessions.
if (PHP_SAPI === 'cli-server') {
  $_SERVER['SCRIPT_NAME'] = '/index.php';
  $_SERVER['PHP_SELF'] = '/index.php';
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
  throw new RuntimeException('Unexpected database; refusing fixture setup.');
}

// Mutations between HTTP requests need a separate kernel too: core deduplicates
// cache-tag invalidations within a request, so repeated parent-process saves
// would not model two administrator requests correctly.
if (($argv[1] ?? '') === 'role') {
  $accounts = json_decode(file_get_contents($fixture . '/auth-fixture.json'), TRUE, 512, JSON_THROW_ON_ERROR);
  $user = User::load($accounts['admin']['uid']);
  if (($argv[2] ?? '') === 'restore') {
    $user->addRole('http_admin');
  }
  else {
    $user->removeRole('http_admin');
  }
  $user->save();
  exit;
}

function administratorRole(bool $enabled): void {
  $process = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', __FILE__, 'role', $enabled ? 'restore' : 'withdraw'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']], $pipes);
  $errors = stream_get_contents($pipes[2]);
  fclose($pipes[2]);
  if (proc_close($process) !== 0) {
    throw new RuntimeException('Fixture role update failed: ' . $errors);
  }
}

$checks = 0;
function check(bool $condition, string $description): void {
  if (!$condition) {
    throw new RuntimeException($description);
  }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $description . PHP_EOL;
}

/** Sends one HTTP request without exposing credentials in diagnostics. */
function http(string $method, string $path, ?string $jar = NULL, ?string $bearer = NULL, ?string $csrf = NULL, string $body = 'null', int $port = 8080): array {
  $curl = curl_init('http://127.0.0.1:' . $port . $path);
  $headers = ['Accept: application/json'];
  if ($bearer !== NULL) {
    $headers[] = 'Authorization: Bearer ' . $bearer;
  }
  if ($csrf !== NULL) {
    $headers[] = 'X-CSRF-Token: ' . $csrf;
  }
  if ($method !== 'GET') {
    $headers[] = 'Content-Type: application/json';
    curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
  }
  if ($jar !== NULL) {
    curl_setopt($curl, CURLOPT_COOKIEFILE, $jar);
    curl_setopt($curl, CURLOPT_COOKIEJAR, $jar);
  }
  curl_setopt_array($curl, [
    CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_FOLLOWLOCATION => FALSE,
    CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 30,
  ]);
  $content = curl_exec($curl);
  $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
  $error = curl_errno($curl);
  curl_close($curl);
  if ($content === FALSE) {
    throw new RuntimeException('Loopback HTTP transport failed: ' . $error);
  }
  return ['status' => $status, 'body' => $content];
}

/** Requires the exact status; a generic error is not a successful access check. */
function status(array $response, int $expected, string $description): array {
  check($response['status'] === $expected, $description . ' (HTTP ' . $response['status'] . ', expected ' . $expected . ')');
  return $response;
}

\Drupal::service('module_installer')->install(['language', 'xinshi_ai']);
$credentials_path = $fixture . '/auth-fixture.json';
if (!is_file($credentials_path)) {
  Role::create(['id' => 'http_author', 'label' => 'HTTP fixture author', 'permissions' => [
    'access content', 'create landing_page content', 'create xinshi_ai image job', 'cancel xinshi_ai image job',
  ]])->save();
  Role::create(['id' => 'http_admin', 'label' => 'HTTP fixture administrator', 'is_admin' => TRUE])->save();
  Oauth2Scope::create([
    'name' => 'http_author', 'granularity_id' => 'role',
    'granularity_configuration' => ['role' => 'http_author'],
    'grant_types' => ['authorization_code' => ['status' => TRUE]],
  ])->save();
  Consumer::create(['label' => 'HTTP fixture consumer', 'client_id' => 'http-fixture'])->save();
  $accounts = [];
  foreach (['ordinary', 'admin', 'blocked'] as $name) {
    $password = bin2hex(random_bytes(24));
    $user = User::create([
      'name' => 'http-fixture-' . $name, 'pass' => $password, 'status' => $name !== 'blocked',
      'roles' => $name === 'admin' ? ['http_author', 'http_admin'] : ['http_author'],
    ]);
    $user->save();
    $accounts[$name] = ['uid' => $user->id(), 'name' => $user->getAccountName(), 'password' => $password];
  }
  file_put_contents($credentials_path, json_encode($accounts, JSON_THROW_ON_ERROR));
  chmod($credentials_path, 0600);
}
$accounts = json_decode(file_get_contents($credentials_path), TRUE, 512, JSON_THROW_ON_ERROR);
check((int) $accounts['admin']['uid'] !== 1, 'Administrator checks do not rely on UID 1');
Role::load('http_author')->grantPermission('edit own landing_page content')->save();
$pages = [];
foreach (['ordinary', 'admin'] as $name) {
  $page = Node::create([
    'type' => 'landing_page', 'title' => 'HTTP auth fixture ' . $name,
    'uid' => $accounts[$name]['uid'], 'status' => 1, 'langcode' => 'en',
  ]);
  $page->save();
  $pages[$name] = $page->id();
}
$node_count = (int) \Drupal::database()->select('node')->countQuery()->execute()->fetchField();

// Issue real persisted JWTs using the installed OAuth repository and signing
// implementation. This tests resource authentication, not the PKCE grant flow.
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $private_key);
file_put_contents($fixture . '/oauth-private.pem', $private_key);
file_put_contents($fixture . '/oauth-public.pem', openssl_pkey_get_details($key)['key']);
chmod($fixture . '/oauth-private.pem', 0600);
chmod($fixture . '/oauth-public.pem', 0600);
\Drupal::configFactory()->getEditable('simple_oauth.settings')
  ->set('private_key', $fixture . '/oauth-private.pem')
  ->set('public_key', $fixture . '/oauth-public.pem')->save();
function issueToken(string $uid, string $expiry = '+1 hour', bool $revoked = FALSE): string {
  $repository = \Drupal::service('simple_oauth.repositories.access_token');
  $client = \Drupal::service('simple_oauth.repositories.client')->getClientEntity('http-fixture');
  $scope = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier('http_author');
  $token = $repository->getNewToken($client, [$scope], $uid);
  $token->setIdentifier(bin2hex(random_bytes(24)));
  $token->setExpiryDateTime(new DateTimeImmutable($expiry));
  $token->setPrivateKey(new CryptKey('/tmp/xinshi-api-test/oauth-private.pem'));
  $repository->persistNewAccessToken($token);
  if ($revoked) {
    $repository->revokeAccessToken($token->getIdentifier());
  }
  return $token->convertToJWT()->toString();
}
$tokens = [];
foreach (['ordinary', 'admin', 'blocked', 'expired', 'revoked'] as $name) {
  $account = $accounts[in_array($name, ['expired', 'revoked'], TRUE) ? 'admin' : $name];
  $tokens[$name] = issueToken((string) $account['uid'], $name === 'expired' ? '-1 hour' : '+1 hour', $name === 'revoked');
}
\Drupal::service('router.builder')->rebuild();

$server = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-S', '127.0.0.1:8080', __FILE__], [
  0 => ['file', '/dev/null', 'r'],
  1 => ['file', $fixture . '/auth-http-server.log', 'a'],
  2 => ['file', $fixture . '/auth-http-server.log', 'a'],
], $pipes);
$proxy = NULL;
try {
  $ready = FALSE;
  for ($i = 0; $i < 40; $i++) {
    $socket = @fsockopen('127.0.0.1', 8080, $errno, $error, 0.1);
    if ($socket) {
      fclose($socket);
      $ready = TRUE;
      break;
    }
    usleep(50000);
  }
  check($ready, 'Loopback HTTP server is ready');
  $sessions = [];
  foreach (['ordinary', 'admin', 'second_admin'] as $name) {
    $account = $accounts[$name === 'second_admin' ? 'admin' : $name];
    $jar = $fixture . '/http-' . $name . '-' . bin2hex(random_bytes(5)) . '.cookies';
    touch($jar);
    chmod($jar, 0600);
    $login = status(http('POST', '/user/login?_format=json', $jar, body: json_encode([
      'name' => $account['name'], 'pass' => $account['password'],
    ], JSON_THROW_ON_ERROR)), 200, 'Real Cookie login: ' . $name);
    check((string) json_decode($login['body'], TRUE)['current_user']['uid'] === (string) $account['uid'],
      'Cookie login identifies the expected account: ' . $name);
    $csrf = status(http('GET', '/session/token', $jar), 200, 'Fetch current session token: ' . $name)['body'];
    check((bool) preg_match('/^[a-zA-Z0-9_-]{43}$/D', $csrf), 'Session token has the expected wire format: ' . $name);
    $sessions[$name] = ['jar' => $jar, 'csrf' => $csrf];
  }
  check($sessions['admin']['csrf'] !== $sessions['second_admin']['csrf'], 'Two sessions for one account have distinct CSRF tokens');
  $admin = $sessions['admin'];
  $ordinary = $sessions['ordinary'];
  $paths = [
    ['POST', '/api/v3/ai/models', 400, 'admin'],
    ['PATCH', '/api/v3/ai/models/http-missing-model', 404, 'admin'],
    ['PATCH', '/api/v3/ai/manage/defaults', 400, 'admin'],
    ['POST', '/api/v3/image-jobs', 400, 'ordinary'],
    ['POST', '/api/v3/image-jobs/12345678-1234-1234-1234-123456789abc/cancel', 404, 'ordinary'],
    ['POST', '/api/v3/landingPage/builder', 422, 'ordinary'],
    ['PATCH', '/api/v3/landingPage/update/' . $pages['ordinary'], 422, 'ordinary'],
    ['POST', '/api/v3/landingPage/alias/' . $pages['ordinary'], 200, 'ordinary'],
    ['POST', '/api/v3/landingPage/translations/add/' . $pages['ordinary'] . '/en/en', 422, 'ordinary'],
    ['POST', '/api/v3/landingPage/translations/delete/' . $pages['ordinary'] . '/en', 200, 'ordinary'],
    ['POST', '/api/v3/node/translations/add/' . $pages['ordinary'] . '/en/en', 200, 'ordinary'],
  ];
  foreach ($paths as [$method, $path, $allowed, $name]) {
    $session = $sessions[$name];
    status(http($method, $path), 403, 'Anonymous denied: ' . $path);
    status(http($method, $path, $session['jar']), 403, 'Cookie without CSRF denied: ' . $path);
    status(http($method, $path, $session['jar'], csrf: str_repeat('x', 43)), 403, 'Cookie with wrong CSRF denied: ' . $path);
    status(http($method, $path, $session['jar'], csrf: $sessions['second_admin']['csrf']), 403, 'Other-session CSRF denied: ' . $path);
    $response = status(http($method, $path, $session['jar'], csrf: $session['csrf']), $allowed, 'Cookie with current CSRF reaches controller: ' . $path);
    if ($allowed === 200) {
      check(json_decode($response['body'], TRUE)['status'] === FALSE, 'Legacy controller returns a validation response without writing: ' . $path);
    }
    status(http($method, $path, bearer: $tokens[$name]), $allowed, 'Bearer without Cookie reaches controller without CSRF: ' . $path);
    status(http($method, $path, $session['jar'], $tokens[$name]), 403, 'Mixed auth without CSRF denied: ' . $path);
    status(http($method, $path, $session['jar'], $tokens[$name], str_repeat('x', 43)), 403, 'Mixed auth with wrong CSRF denied: ' . $path);
    status(http($method, $path, $session['jar'], $tokens[$name], $session['csrf']), $allowed, 'Mixed auth with current CSRF reaches controller: ' . $path);
  }
  $defaults = '/api/v3/ai/manage/defaults';
  status(http('PATCH', $defaults, $ordinary['jar'], csrf: $ordinary['csrf']), 403, 'Valid CSRF does not grant model administration');
  status(http('PATCH', $defaults, $admin['jar'], $tokens['ordinary'], $admin['csrf']), 403, 'Ordinary Bearer cannot borrow the administrator Cookie permissions');
  status(http('PATCH', $defaults, $ordinary['jar'], $tokens['admin'], $ordinary['csrf']), 400, 'Administrator Bearer takes priority over the ordinary Cookie identity');
  $other_page = '/api/v3/landingPage/update/' . $pages['admin'];
  status(http('PATCH', $other_page, $ordinary['jar'], csrf: $ordinary['csrf']), 403, 'Valid Cookie CSRF does not grant another author page access');
  status(http('PATCH', $other_page, bearer: $tokens['ordinary']), 403, 'Valid Bearer does not grant another author page access');
  status(http('PATCH', '/api/v3/landingPage/update/' . $pages['ordinary'], bearer: $tokens['admin']), 422, 'Administrator can reach another author page controller');

  // Safe real persistence: clearing a default changes only this fixture config.
  status(http('PATCH', $defaults, $admin['jar'], csrf: $admin['csrf'], body: '{"chat":null}'), 200, 'Cookie and CSRF authorize a real defaults write');
  $list = status(http('GET', '/api/v3/ai/manage/models', bearer: $tokens['admin']), 200, 'Administrator Bearer can read management without CSRF');
  check(!isset(json_decode($list['body'], TRUE)['defaults']['chat']), 'A fresh HTTP read observes the persisted defaults change');
  status(http('PATCH', $defaults, bearer: $tokens['admin'], body: '{}'), 200, 'Bearer without Cookie authorizes a real defaults write');

  foreach (['ordinary', 'admin', 'admin', 'ordinary'] as $name) {
    status(http('GET', '/api/v3/ai/manage/models', bearer: $tokens[$name]), $name === 'admin' ? 200 : 403,
      'Management permission remains isolated while alternating OAuth accounts: ' . $name);
  }

  // Model the two Cookie policies separately; this is not a production Nginx
  // configuration or a test of its TLS, caching, CORS or location precedence.
  file_put_contents($fixture . '/auth-proxy.conf', <<<'NGINX'
pid /tmp/xinshi-api-test/auth-proxy.pid;
error_log /tmp/xinshi-api-test/auth-proxy.log;
events { worker_connections 32; }
http {
  access_log off;
  server {
    listen 127.0.0.1:8081;
    location / {
      proxy_set_header Host 127.0.0.1:8080;
      proxy_pass http://127.0.0.1:8080;
    }
    location /api/ {
      proxy_set_header Host 127.0.0.1:8080;
      proxy_set_header Cookie "";
      proxy_hide_header Set-Cookie;
      proxy_pass http://127.0.0.1:8080;
    }
  }
  server {
    listen 127.0.0.1:8082;
    location / {
      proxy_set_header Host 127.0.0.1:8080;
      proxy_pass http://127.0.0.1:8080;
    }
  }
}
NGINX);
  $proxy = proc_open(['nginx', '-c', $fixture . '/auth-proxy.conf', '-g', 'daemon off;'], [
    0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'],
    2 => ['file', $fixture . '/auth-proxy.log', 'a'],
  ], $pipes);
  for ($i = 0; $i < 40; $i++) {
    $socket = @fsockopen('127.0.0.1', 8082, $errno, $error, 0.1);
    if ($socket) {
      fclose($socket);
      break;
    }
    usleep(50000);
  }
  foreach ([8081, 8082] as $port) {
    check(http('GET', '/session/token', $admin['jar'], port: $port)['body'] === $admin['csrf'],
      'Proxy token endpoint retains the current Cookie session: ' . $port);
  }
  status(http('PATCH', $defaults, $admin['jar'], csrf: $admin['csrf'], body: '{}', port: 8081), 403,
    'Stripping proxy cannot authenticate a Cookie-only write');
  status(http('PATCH', $defaults, $admin['jar'], $tokens['admin'], body: '{}', port: 8081), 200,
    'Stripping proxy preserves Bearer and removes the CSRF-requiring Cookie');
  status(http('PATCH', $defaults, $admin['jar'], $tokens['admin'], body: '{}', port: 8082), 403,
    'Cookie-preserving proxy requires CSRF even with Bearer');
  status(http('PATCH', $defaults, $admin['jar'], csrf: $admin['csrf'], body: '{}', port: 8082), 200,
    'Cookie-preserving proxy accepts Cookie writes with the current CSRF token');
  status(http('PATCH', $defaults, $admin['jar'], $tokens['admin'], $admin['csrf'], '{}', 8082), 200,
    'Cookie-preserving proxy accepts mixed authentication with the current CSRF token');
  foreach (['blocked', 'expired', 'revoked', 'malformed'] as $name) {
    $bearer = $tokens[$name] ?? 'not-a-valid-jwt';
    status(http('PATCH', $defaults, bearer: $bearer), 401, 'Unusable Bearer is rejected: ' . $name);
    status(http('PATCH', $defaults, $admin['jar'], $bearer, $admin['csrf']), 401, 'Unusable Bearer does not fall back to a valid Cookie: ' . $name);
  }

  // Simple OAuth deletes persisted tokens on user updates. Verify both the old
  // token's rejection and the new permissions after a fresh token is issued.
  administratorRole(FALSE);
  try {
    status(http('GET', '/api/v3/ai/manage/models', bearer: $tokens['admin']), 401, 'Updating account roles invalidates the existing JWT');
    $limited_token = issueToken((string) $accounts['admin']['uid']);
    status(http('GET', '/api/v3/ai/manage/models', bearer: $limited_token), 403, 'Fresh JWT after role withdrawal has no administrator permission');
    status(http('PATCH', $defaults, $admin['jar'], csrf: $admin['csrf']), 403, 'Revoking the administrator role also takes effect on Cookie writes');
  }
  finally {
    administratorRole(TRUE);
  }
  status(http('GET', '/api/v3/ai/manage/models', bearer: $tokens['admin']), 401, 'Restoring a role does not revive an invalidated JWT');
  status(http('GET', '/api/v3/ai/manage/models', bearer: issueToken((string) $accounts['admin']['uid'])), 200,
    'Fresh JWT after role restoration has administrator permission');

  // Expire only sessions belonging to this runner's fixture accounts.
  \Drupal::database()->delete('sessions')->condition('uid', $accounts['ordinary']['uid'])->execute();
  status(http('POST', '/api/v3/image-jobs', $ordinary['jar'], csrf: $ordinary['csrf']), 403, 'Expired Cookie plus old CSRF cannot authorize a write');
  check((int) \Drupal::database()->select('node')->countQuery()->execute()->fetchField() === $node_count,
    'Rejected and validation-only requests created no additional page or image job entities');
  check(\Drupal::queue('xinshi_ai_image_job')->numberOfItems() === 0, 'No provider work was enqueued');
  echo 'Isolated HTTP authentication checks passed: ' . $checks . '.' . PHP_EOL;
}
finally {
  if (is_resource($proxy)) {
    proc_terminate($proxy);
    proc_close($proxy);
  }
  proc_terminate($server);
  proc_close($server);
}
