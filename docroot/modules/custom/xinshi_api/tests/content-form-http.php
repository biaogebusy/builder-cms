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
$fixture = '/tmp/content-form-test';
if (getenv('XINSHI_CONTENT_FORM_TEST') !== '1' || !is_file('/.dockerenv') ||
  $root !== '/app' || !is_file($fixture . '/site.sqlite')) {
  throw new RuntimeException('Use the disposable content form runner only.');
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
  if (str_contains($path, '/api/v3/node-form/') || str_contains($path, '/api/v3/comment-form/')) {
    check(str_contains($cache, 'no-store') && str_contains($cache, 'private'), 'API response is private/no-store: ' . $status);
  }
  return ['status' => $status, 'data' => json_decode($content, TRUE), 'body' => $content];
}
function status(array $response, int $expected, string $label): array {
  if ($response['status'] !== $expected) {
    echo json_encode($response['data']['errors'] ?? $response['data']) . PHP_EOL;
  }
  check($response['status'] === $expected, $label . ' (HTTP ' . $response['status'] . ', expected ' . $expected . ')');
  if ($expected >= 400 && isset($response['data']['code'])) {
    check(!array_diff(array_keys($response['data'] ?? []), ['code', 'executionId', 'outcome']),
      'API error contains no exception or request detail');
  }
  return $response['data'] ?? [];
}
\Drupal::configFactory()->getEditable('jsonapi.settings')->set('read_only', FALSE)->save();
\Drupal::service('module_installer')->install(['simple_oauth']);
$writer = User::load(reset(\Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => 'writer']))->id());
$other = reset(\Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => 'other']));
$password = bin2hex(random_bytes(24));
$writer->setPassword($password)->save();
Role::create(['id' => 'reader', 'label' => 'Reader', 'permissions' => ['access content']])->save();
$writer->addRole('reader')->save();
foreach (['writer', 'reader'] as $role) {
  Oauth2Scope::create(['name' => $role, 'granularity_id' => 'role',
    'granularity_configuration' => ['role' => $role],
    'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
}
Consumer::create(['label' => 'Form test', 'client_id' => 'form-test'])->save();
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $private);
file_put_contents($fixture . '/private.pem', $private);
file_put_contents($fixture . '/public.pem', openssl_pkey_get_details($key)['key']);
chmod($fixture . '/private.pem', 0600);
\Drupal::configFactory()->getEditable('simple_oauth.settings')->set('private_key', $fixture . '/private.pem')
  ->set('public_key', $fixture . '/public.pem')->save();
function token(string $uid, string $scope): string {
  $repo = \Drupal::service('simple_oauth.repositories.access_token');
  $client = \Drupal::service('simple_oauth.repositories.client')->getClientEntity('form-test');
  $scopeEntity = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier($scope);
  $token = $repo->getNewToken($client, [$scopeEntity], $uid);
  $token->setIdentifier(bin2hex(random_bytes(24)));
  $token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
  $token->setPrivateKey(new CryptKey('/tmp/content-form-test/private.pem'));
  $repo->persistNewAccessToken($token);
  return $token->convertToJWT()->toString();
}
$full = token((string) $writer->id(), 'writer');
$read = token((string) $writer->id(), 'reader');
$otherToken = token((string) $other->id(), 'writer');
\Drupal\comment\Entity\CommentType::create(['id' => 'blog_comment', 'label' => 'Blog comment', 'target_entity_type_id' => 'node'])->save();
\Drupal\field\Entity\FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => 'field_comments',
  'type' => 'comment', 'settings' => ['comment_type' => 'blog_comment']])->save();
\Drupal\field\Entity\FieldConfig::create(['entity_type' => 'node', 'bundle' => 'blog', 'field_name' => 'field_comments',
  'default_value' => [['status' => 2]], 'settings' => ['default_mode' => 1, 'per_page' => 10]])->save();
\Drupal\comment\Entity\CommentType::load('blog_comment')->set('description', 'Synthetic comments')->save();
\Drupal::service('comment.manager')->addBodyField('blog_comment');
\Drupal::service('router.builder')->rebuild();
$log = $fixture . '/http.log';
$server = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-S', '127.0.0.1:8080', __FILE__],
  [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root . '/docroot');
try {
  for ($attempt = 0; $attempt < 100; $attempt++) {
    $socket = @fsockopen('127.0.0.1', 8080);
    if ($socket) { fclose($socket); break; }
    usleep(50000);
  }
  status(http('GET', '/api/v3/node-form/blog'), 403, 'Anonymous form denied');
  status(http('GET', '/api/v3/node-form/blog', bearer: 'invalid'), 401, 'Invalid bearer cannot discover form');
  $legacy = status(http('GET', '/jsonapi/field_config/field_config?filter[entity_type]=node&filter[bundle]=blog', bearer: $full), 200, 'Legacy configuration request succeeds as a collection');
  check($legacy['data'] === [] && isset($legacy['meta']['omitted']), 'Original ordinary-user failure is reproduced');
  $types = status(http('GET', '/api/v3/node-form/types', bearer: $full), 200, 'Writer can discover types');
  check(array_column($types['data'], 'value') === ['blog'], 'Discovery returns only creatable types');
  check(status(http('GET', '/api/v3/node-form/types', bearer: $read), 200, 'Reader discovery')['data'] === [], 'Same user with limited scope cannot create');
  status(http('GET', '/api/v3/node-form/blog', bearer: $read), 403, 'Reader scope cannot use writer form');
  $form = status(http('GET', '/api/v3/node-form/blog', bearer: $full), 200, 'Writer loads form without administration');
  check($form['fields'][0]['textFormat'] === 'basic_html', 'HTTP description uses effective OAuth format permissions');
  $body = ['data' => ['type' => 'node--blog', 'attributes' => ['title' => 'Created through API',
    'body' => ['value' => '<p>Blog</p>', 'format' => $form['fields'][0]['textFormat']]]]];
  $created = status(http('POST', '/jsonapi/node/blog', bearer: $full, body: json_encode($body), type: 'application/vnd.api+json'), 201, 'Ordinary writer creates blog with default publication');
  $uuid = $created['data']['id'];
  check($created['data']['attributes']['status'] === TRUE, 'Default published status needs no status administration');
  status(http('GET', '/api/v3/node-form/blog/' . $uuid, bearer: $full), 200, 'Owner loads edit form');
  status(http('GET', '/api/v3/node-form/blog/' . $uuid, bearer: $otherToken), 403, 'Other writer cannot load edit form');
  $patch = ['data' => ['type' => 'node--blog', 'id' => $uuid, 'attributes' => ['title' => 'Updated']]];
  status(http('PATCH', '/jsonapi/node/blog/' . $uuid, bearer: $full, body: json_encode($patch), type: 'application/vnd.api+json'), 200, 'Owner updates blog');
  status(http('PATCH', '/jsonapi/node/blog/' . $uuid, bearer: $otherToken, body: json_encode($patch), type: 'application/vnd.api+json'), 403, 'Other writer update rejected');
  $body['data']['attributes']['status'] = FALSE;
  status(http('POST', '/jsonapi/node/blog', bearer: $full, body: json_encode($body), type: 'application/vnd.api+json'), 403, 'Forged published-status override rejected');
  unset($body['data']['attributes']['status']);
  $body['data']['attributes']['body']['format'] = 'full_html';
  status(http('POST', '/jsonapi/node/blog', bearer: $full, body: json_encode($body), type: 'application/vnd.api+json'), 422, 'Forbidden text format rejected on write');
  $jar = $fixture . '/cookie.txt';
  status(http('POST', '/user/login?_format=json', jar: $jar, body: json_encode(['name' => 'writer', 'pass' => $password])), 200, 'Cookie login');
  status(http('GET', '/api/v3/node-form/blog', jar: $jar), 200, 'Cookie form uses content permissions');
  status(http('GET', '/api/v3/node-form/blog', bearer: $read, jar: $jar), 403, 'Cookie cannot widen a limited bearer');
  $commentForm = status(http('GET', '/api/v3/comment-form/blog_comment', bearer: $full), 200, 'Comment format discovery needs no field administration');
  check($commentForm['textFormat'] === 'basic_html', 'Comment body uses a permitted format');
  $comment = ['data' => ['type' => 'comment--blog_comment', 'attributes' => ['entity_type' => 'node',
    'field_name' => 'field_comments', 'comment_body' => ['value' => '<p>Comment</p>', 'format' => 'basic_html']],
    'relationships' => ['entity_id' => ['data' => ['type' => 'node--blog', 'id' => $uuid]]]]];
  $result = status(http('POST', '/jsonapi/comment/blog_comment', bearer: $full, body: json_encode($comment), type: 'application/vnd.api+json'), 201, 'Ordinary user posts a comment without uid override');
  // Granting skip approval is unrelated to metadata access; approve the synthetic record as the host.
  $commentEntity = reset(\Drupal::entityTypeManager()->getStorage('comment')->loadByProperties(['uuid' => $result['data']['id']]));
  $commentEntity->setPublished()->save();
  $commentUuid = $commentEntity->uuid();
  status(http('GET', '/api/v3/comment-form/blog_comment/' . $commentUuid, bearer: $full), 200, 'Comment owner discovers original format');
  status(http('GET', '/api/v3/comment-form/blog_comment/' . $commentUuid, bearer: $otherToken), 403, 'Other author cannot describe comment edit');
  $patch = ['data' => ['type' => 'comment--blog_comment', 'id' => $commentUuid,
    'attributes' => ['comment_body' => ['value' => '<p>Edit</p>', 'format' => 'basic_html']]]];
  status(http('PATCH', '/jsonapi/comment/blog_comment/' . $commentUuid, bearer: $full, body: json_encode($patch), type: 'application/vnd.api+json'), 200, 'Comment owner edits without administrator-only uid');
  Role::load('writer')->revokePermission('create blog content')->save();
  status(http('GET', '/api/v3/node-form/blog', bearer: $full), 403, 'Revocation affects later form requests');
  $body['data']['attributes']['body']['format'] = 'basic_html';
  status(http('POST', '/jsonapi/node/blog', bearer: $full, body: json_encode($body), type: 'application/vnd.api+json'), 403, 'Revocation also rejects writes after an earlier form load');
  require __DIR__ . '/page-republication-http.php';
  echo "Content form HTTP checks: $checks\n";
}
finally {
  proc_terminate($server);
  proc_close($server);
  if (!isset($commentUuid) && is_file($log)) { echo file_get_contents($log); }
}
