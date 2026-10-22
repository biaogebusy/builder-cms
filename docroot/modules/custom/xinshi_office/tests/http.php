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
$fixture = '/tmp/office-test';
if (getenv('XINSHI_OFFICE_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  $root !== '/app' || !is_file($fixture . '/site.sqlite')) {
  throw new RuntimeException('Use the disposable Office runner only.');
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
  string|array $body = '', string $type = 'application/json', string $csrf = ''): array {
  $curl = curl_init('http://127.0.0.1:8080' . $path);
  $headers = ['Accept: ' . ($type === 'application/x-www-form-urlencoded' ? 'text/html' : 'application/json'),
    'Content-Type: ' . $type];
  if (is_array($body)) array_pop($headers);
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
  if (str_starts_with($path, '/api/v3/ai/office-') || str_starts_with($path, '/admin/content/office-documents')) {
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

\Drupal::service('module_installer')->install(['simple_oauth']);
$grants = ['full' => ['read own office documents', 'edit own office documents'],
  'read' => ['read own office documents'], 'none' => ['access content']];
foreach ($grants as $name => $permissions) {
  $id = 'office_http_' . $name;
  Role::create(['id' => $id, 'label' => $id, 'permissions' => $permissions])->save();
  Oauth2Scope::create(['name' => $id, 'granularity_id' => 'role',
    'granularity_configuration' => ['role' => $id],
    'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
}
Consumer::create(['label' => 'Office HTTP', 'client_id' => 'office-http'])->save();
$password = bin2hex(random_bytes(24));
$user = User::create(['name' => 'office-http', 'pass' => $password, 'status' => 1,
  'roles' => array_map(fn($name) => 'office_http_' . $name, array_keys($grants))]); $user->save();
$other = User::create(['name' => 'office-http-other', 'status' => 1, 'roles' => ['office_http_full']]); $other->save();
$blocked = User::create(['name' => 'office-http-blocked', 'status' => 0, 'roles' => ['office_http_full']]); $blocked->save();
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $private);
file_put_contents($fixture . '/private.pem', $private);
file_put_contents($fixture . '/public.pem', openssl_pkey_get_details($key)['key']);
chmod($fixture . '/private.pem', 0600);
\Drupal::configFactory()->getEditable('simple_oauth.settings')->set('private_key', $fixture . '/private.pem')
  ->set('public_key', $fixture . '/public.pem')->save();
function token(string $uid, string $scope, string $expiry = '+1 hour', bool $revoked = FALSE): string {
  $repo = \Drupal::service('simple_oauth.repositories.access_token');
  $client = \Drupal::service('simple_oauth.repositories.client')->getClientEntity('office-http');
  $scopeEntity = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier('office_http_' . $scope);
  $token = $repo->getNewToken($client, [$scopeEntity], $uid);
  $token->setIdentifier(bin2hex(random_bytes(24)));
  $token->setExpiryDateTime(new DateTimeImmutable($expiry));
  $token->setPrivateKey(new CryptKey('/tmp/office-test/private.pem'));
  $repo->persistNewAccessToken($token);
  if ($revoked) $repo->revokeAccessToken($token->getIdentifier());
  return $token->convertToJWT()->toString();
}
$tokens = [];
foreach (array_keys($grants) as $scope) $tokens[$scope] = token((string) $user->id(), $scope);
$tokens['other'] = token((string) $other->id(), 'full');
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
      fclose($socket); $ready = TRUE; break;
    }
    usleep(50000);
  }
  check($ready, 'Loopback HTTP server ready');
  $base = '/api/v3/ai/office-documents';
  $jar = $fixture . '/cookie.txt';
  $login = status(http('POST', '/user/login?_format=json', jar: $jar,
    body: json_encode(['name' => $user->getAccountName(), 'pass' => $password])), 200, 'Cookie login');
  status(http('GET', $base . '/capabilities'), 403, 'Anonymous cannot discover Office data');
  foreach (['invalid', 'blocked', 'expired', 'revoked'] as $bad) {
    status(http('GET', $base . '/capabilities', $tokens[$bad] ?? 'invalid-jwt', $jar), 401,
      'Invalid Bearer cannot borrow Cookie identity');
  }
  foreach (['full', 'read', 'full', 'read'] as $scope) {
    $cap = status(http('GET', $base . '/capabilities', $tokens[$scope]), 200, 'Effective OAuth capabilities');
    check($cap['writeTypes'] === ($scope === 'full' ? ['word', 'sheet'] : []), 'Full account roles cannot widen a scoped token');
    check($cap['uid'] === (string) $user->id(), 'Capabilities bind to the authenticated source account');
  }
  status(http('GET', $base, $tokens['none'], $jar), 403, 'Cookie cannot elevate limited Bearer scope');
  $input = ['operationId' => \Drupal::service('uuid')->generate(), 'name' => 'HTTP document', 'kind' => 'word',
    'source' => 'manual', 'chatId' => NULL, 'originalFileId' => NULL, 'importReport' => NULL, 'assetIds' => [],
    'content' => ['version' => 1, 'type' => 'doc', 'content' => [
      ['type' => 'paragraph', 'attrs' => ['id' => 'p1'], 'content' => [['type' => 'text', 'text' => 'HTTP 工作稿']]],
    ]]];
  $body = json_encode($input, JSON_THROW_ON_ERROR);
  status(http('POST', $base, jar: $jar, body: $body), 403, 'Cookie write without CSRF rejected');
  status(http('POST', $base, jar: $jar, body: $body, csrf: 'wrong'), 403, 'Wrong CSRF rejected');
  $csrf = trim(http('GET', '/session/token', jar: $jar)['body']);
  status(http('POST', $base, $tokens['read'], $jar, $body, csrf: $csrf), 403, 'Valid CSRF does not grant write scope');
  $receipt = status(http('POST', $base, $tokens['full'], body: $body), 200, 'Bearer-only document creation');
  check(status(http('POST', $base, $tokens['full'], body: $body), 200, 'Duplicate HTTP creation') === $receipt,
    'Repeated create returns the exact original receipt');
  $path = $base . '/' . $receipt['documentId'];
  check(canonical(status(http('GET', $path, $tokens['read']), 200, 'Read-only owner can reopen')['content']) === canonical($input['content']),
    'Fresh HTTP read restores actual content');
  check(status(http('GET', $base . '/operations/' . $input['operationId'], $tokens['read']), 200, 'Read-only receipt lookup') === $receipt,
    'Receipt recovery does not require write scope');
  foreach ([$path, $path . '/revisions', $base . '/operations/' . $input['operationId']] as $private_path) {
    status(http('GET', $private_path, $tokens['other']), 404, 'Other owner cannot read document or receipt');
  }
  $update = ['operationId' => \Drupal::service('uuid')->generate(), 'expectedRevision' => 1, 'name' => 'HTTP edited',
    'source' => 'manual', 'assetIds' => [], 'content' => $input['content']];
  $changed = status(http('PATCH', $path, jar: $jar, body: json_encode($update), csrf: $csrf), 200, 'Cookie update with valid CSRF');
  check($changed['revision'] === 2, 'HTTP update advances exactly one revision');
  $update['operationId'] = \Drupal::service('uuid')->generate();
  $refusal = status(http('PATCH', $path, $tokens['full'], body: json_encode($update)), 409, 'Stale HTTP revision is rejected');
  check($refusal === ['code' => 'revision_conflict', 'outcome' => 'not_written',
    'executionId' => $update['operationId']], 'CAS refusal binds the exact uncommitted operation');
  status(http('GET', $base . '/operations/' . $update['operationId'], $tokens['full']), 404,
    'Rejected operation has no receipt');
  $collision = $input;
  $collision['name'] = 'Different approved input';
  $conflict = status(http('POST', $base, $tokens['full'], body: json_encode($collision)), 409,
    'Operation ID reuse with different input is rejected');
  check($conflict === ['code' => 'operation_id_conflict'], 'A colliding receipt is not proof of no earlier write');
  check(status(http('GET', $path . '?revision=1', $tokens['full']), 200, 'Historical snapshot')['name'] === $input['name'],
    'Historical name survives later edits');
  status(http('GET', $path . '?revision=0', $tokens['full']), 400, 'Invalid revision query rejected');
  status(http('POST', $base, $tokens['full'], body: '[]'), 422, 'Array cannot replace object protocol');
  status(http('POST', $base, $tokens['full'], body: '{'), 400, 'Invalid JSON is bounded and sanitized');
  $sheet_input = $input;
  $sheet_input['operationId'] = \Drupal::service('uuid')->generate();
  $sheet_input['kind'] = 'sheet';
  $sheet_input['content'] = ['version' => 1, 'type' => 'workbook', 'dateSystem' => '1900', 'styles' => (object) [],
    'sheets' => [['id' => 'data', 'name' => 'Data', 'rows' => 100, 'columns' => 10,
      'defaultRowHeight' => 15, 'defaultColumnWidth' => 8.43, 'rowSizes' => [], 'columnSizes' => [],
      'merges' => [], 'freeze' => ['rows' => 0, 'columns' => 0], 'cells' => [
        ['row' => 0, 'column' => 0, 'value' => ['type' => 'text', 'value' => '00123']],
        ['row' => 1, 'column' => 0, 'value' => ['type' => 'formula', 'formula' => '=SUM(1,2)']],
      ]]]];
  $sheet_body = json_encode($sheet_input);
  status(http('POST', $base, $tokens['read'], body: $sheet_body), 403, 'Spreadsheet cannot widen read-only OAuth scope');
  $sheet_receipt = status(http('POST', $base, jar: $jar, body: $sheet_body, csrf: $csrf), 200,
    'Spreadsheet Cookie creation uses the same CSRF and source authority');
  $sheet_path = $base . '/' . $sheet_receipt['documentId'];
  check(canonical(status(http('GET', $sheet_path, $tokens['read']), 200, 'Spreadsheet source read')['content']) ===
    canonical(json_decode(json_encode($sheet_input['content']), TRUE)),
    'HTTP retains native spreadsheet types and formulas');
  status(http('GET', $sheet_path, $tokens['other']), 404, 'Spreadsheet cells remain owner-only');
  $sheet_input['operationId'] = \Drupal::service('uuid')->generate();
  $sheet_input['content']['sheets'][0]['cells'][1]['value']['formula'] = '=INDIRECT("A1")';
  $invalid_sheet = status(http('POST', $base, $tokens['full'], body: json_encode($sheet_input)), 422,
    'Unsupported formula is rejected by the real HTTP endpoint');
  check($invalid_sheet['outcome'] === 'not_written', 'Invalid spreadsheet carries confirmed non-write semantics');
  $file_id = \Drupal::service('uuid')->generate();
  $upload_body = ['metadata' => json_encode(['fileId' => $file_id, 'kind' => 'word', 'role' => 'image', 'name' => 'HTTP.png']),
    'file' => new CURLFile('/tmp/office-test/image.png', 'image/png', 'HTTP.png')];
  $file_base = '/api/v3/ai/office-files';
  status(http('POST', $file_base, jar: $jar, body: $upload_body), 403, 'Upload enforces Cookie CSRF');
  $file = status(http('POST', $file_base, $tokens['full'], body: $upload_body), 200, 'Actual multipart raster upload');
  check(status(http('GET', $file_base . '/' . $file_id, $tokens['read']), 200, 'Recover upload metadata') === $file,
    'Upload recovery returns stable digest and dimensions');
  status(http('GET', $file_base . '/' . $file_id, $tokens['other']), 404, 'Other owner cannot read upload metadata');
  status(http('GET', $path . '/files/' . $file_id, $tokens['full']), 404, 'Owned but unattached resource cannot be downloaded through a document');
  $update['operationId'] = \Drupal::service('uuid')->generate(); $update['expectedRevision'] = 2;
  $update['assetIds'] = [$file_id];
  $update['content']['content'][] = ['type' => 'image', 'attrs' => ['id' => 'img1', 'assetId' => $file['sha256'],
    'width' => 20, 'height' => 30, 'alt' => 'HTTP diagram']];
  status(http('PATCH', $path, $tokens['full'], body: json_encode($update)), 200, 'Save verified resource reference');
  $download = http('GET', $path . '/files/' . $file_id, $tokens['read']);
  check($download['status'] === 200 && hash('sha256', $download['body']) === $file['sha256'], 'Binary HTTP download preserves exact bytes');
  status(http('GET', $path . '/files/' . $file_id, $tokens['other']), 404, 'Foreign owner cannot download bytes');
  $generic = http('GET', '/system/files/xinshi-office/' . $user->id() . '/' . $file_id, jar: $jar);
  check($generic['status'] === 403, 'Generic private download cannot bypass the Office API');
  require __DIR__ . '/admin-http.php';
  echo 'Office HTTP integration: ' . $checks . ' checks passed.' . PHP_EOL;
}
finally {
  proc_terminate($server);
  proc_close($server);
}
