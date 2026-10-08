<?php

declare(strict_types=1);

/**
 * @file
 * Reference persistence and real JSON:API requests on the disposable synthetic site.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
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
$checks = 0;
set_exception_handler(static function (Throwable $error): void {
  fwrite(STDERR, $error->getMessage() . PHP_EOL);
  exit(1);
});
function check(bool $ok, string $message): void {
  if (!$ok) {
    throw new RuntimeException($message);
  }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function rejected(callable $callback, string $message): void {
  try {
    $callback();
  }
  catch (Throwable $error) {
    check(str_contains($error->getMessage(), 'Invalid protected task reference') ||
      $error->getMessage() === 'Invalid conversation fields.', $message);
    return;
  }
  throw new RuntimeException('Unexpected success: ' . $message);
}
function field(string $bundle, string $name, string $type, array $settings = [], int $cardinality = 1): void {
  FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => $name,
    'type' => $type, 'settings' => $settings, 'cardinality' => $cardinality])->save();
  FieldConfig::create(['entity_type' => 'node', 'bundle' => $bundle, 'field_name' => $name])->save();
}
// Remove fixture grants so the reference module cannot accidentally inherit owner filtering.
\Drupal::service('module_installer')->uninstall(['xinshi_analytics_test']);
\Drupal::service('module_installer')->install(['xinshi_ai_reference', 'basic_auth', 'views', 'rest']);
foreach (['ai_session', 'conversation'] as $bundle) {
  NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
}
field('ai_session', 'content', 'string_long');
field('ai_session', 'session_role', 'string');
field('ai_session', 'summary', 'string_long');
field('ai_session', 'reasoning_content', 'string_long');
field('ai_session', 'total_tokens', 'integer');
field('ai_session', 'future_copy', 'string_long');
field('conversation', 'sessions', 'entity_reference', ['target_type' => 'node'], -1);
field('conversation', 'ai_optimized', 'boolean');
\Drupal::configFactory()->getEditable('jsonapi.settings')->set('read_only', FALSE)->save();
$role = Role::create(['id' => 'reference_owner', 'label' => 'Reference owner', 'permissions' => [
  'access content', 'create ai_session content', 'edit own ai_session content',
  'edit own conversation content', 'delete own ai_session content',
]]);
$role->save();
Oauth2Scope::create(['name' => 'reference_owner', 'granularity_id' => 'role',
  'granularity_configuration' => ['role' => 'reference_owner'],
  'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
$users = [];
foreach (['owner', 'other'] as $name) {
  $users[$name] = User::create(['name' => 'reference-' . $name, 'pass' => 'test-only-reference',
    'status' => 1, 'roles' => ['reference_owner']]);
  $users[$name]->save();
}
\Drupal::currentUser()->setAccount($users['owner']);
$conversation = Node::create(['type' => 'conversation', 'title' => 'Question',
  'uid' => $users['owner']->id(), 'status' => 1]);
$conversation->save();
$otherConversation = Node::create(['type' => 'conversation', 'title' => 'Other question',
  'uid' => $users['owner']->id(), 'status' => 1]);
$otherConversation->save();
$runId = \Drupal::service('uuid')->generate();
$ref = ['kind' => 'xinshi-protected-run', 'version' => 1, 'runId' => $runId,
  'chatId' => $conversation->uuid()];
$values = ['type' => 'ai_session', 'uuid' => $runId, 'title' => 'Protected task result',
  'uid' => $users['owner']->id(), 'status' => 1, 'content' => json_encode($ref),
  'session_role' => 'assistant', 'total_tokens' => 0];
$session = Node::create($values);
check($session->validate()->count() === 0, 'A locator validates without report or analytics grants');
$session->save();
check($session->get('content')->value === json_encode($ref), 'Only locator fields persisted');
foreach (['summary', 'reasoning_content', 'future_copy', 'title'] as $fieldName) {
  $copy = clone $session;
  $copy->set($fieldName, 'private-count-123');
  check($copy->validate()->count() > 0, 'Validation rejects report copy in ' . $fieldName);
  rejected(fn() => $copy->save(), 'Presave rejects report copy in ' . $fieldName);
}
foreach ([['kind' => 'ordinary'], $ref + ['result' => 123], [...$ref, 'chatId' => $otherConversation->uuid()]] as $bad) {
  $copy = clone $session;
  $copy->set('content', json_encode($bad));
  check($copy->validate()->count() > 0, 'Update cannot remove, extend or rebind the locator');
}
$copy = clone $session;
$copy->setOwnerId($users['other']->id());
rejected(fn() => $copy->save(), 'Protected session ownership cannot change');
$storage = \Drupal::entityTypeManager()->getStorage('node');
$storage->resetCache();
$session = $storage->load($session->id());
$conversation->set('sessions', [['target_id' => $session->id()]]);
$conversation->save();
$otherConversation->set('sessions', [['target_id' => $session->id()]]);
check($otherConversation->validate()->count() > 0, 'Relationship validation rejects another conversation');
rejected(fn() => $otherConversation->save(), 'Direct relationship saves reject another conversation');
require_once $root . '/docroot/modules/custom/xinshi_ai/src/Service/ConversationWriter.php';
$writer = new \Drupal\xinshi_ai\Service\ConversationWriter(\Drupal::database(),
  \Drupal::entityTypeManager(), \Drupal::currentUser(), \Drupal::service('jsonapi.resource_type.repository'));
$writer->update($conversation->uuid(), [$runId], []);
check(TRUE, 'ConversationWriter accepts the original conversation idempotently');
rejected(fn() => $writer->update($otherConversation->uuid(), [$runId], []),
  'ConversationWriter cannot rebind a protected session');
node_access_rebuild();
// Exercise old field-based Views, which do not call entity access for each SQL row.
\Drupal\views\Entity\View::create([
  'id' => 'reference_sessions', 'label' => 'Reference sessions fixture', 'base_table' => 'node_field_data',
  'display' => ['default' => ['id' => 'default', 'display_plugin' => 'default', 'display_options' => [
    'access' => ['type' => 'none'], 'cache' => ['type' => 'none'],
    'query' => ['type' => 'views_query', 'options' => ['disable_sql_rewrite' => TRUE]],
    'fields' => ['title' => ['id' => 'title', 'table' => 'node_field_data', 'field' => 'title', 'plugin_id' => 'field'],
      'summary' => ['id' => 'summary', 'table' => 'node__summary', 'field' => 'summary', 'plugin_id' => 'field']],
    'filters' => ['type' => ['id' => 'type', 'table' => 'node_field_data', 'field' => 'type',
      'value' => ['ai_session' => 'ai_session'], 'plugin_id' => 'bundle']],
  ]]],
])->save();
\Drupal::currentUser()->setAccount($users['other']);
$view = \Drupal\views\Views::getView('reference_sessions');
$view->execute();
check(count($view->result) === 1, 'Synthetic legacy View actually reads the protected session');
foreach ($view->result as $row) {
  check($view->field['title']->getValue($row) === 'Protected task result' &&
    !$view->field['summary']->getValue($row), 'Legacy View contains only fixed title and empty summary');
}
\Drupal::currentUser()->setAccount($users['owner']);
\Drupal::service('router.builder')->rebuild();
$repo = \Drupal::service('simple_oauth.repositories.access_token');
$client = \Drupal::service('simple_oauth.repositories.client')->getClientEntity('analytics-http');
$scope = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier('reference_owner');
$bearers = [];
foreach ($users as $name => $user) {
  $token = $repo->getNewToken($client, [$scope], (string) $user->id());
  $token->setIdentifier(bin2hex(random_bytes(24)));
  $token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
  $token->setPrivateKey(new CryptKey('/tmp/analytics-test/private.pem'));
  $repo->persistNewAccessToken($token);
  $bearers[$name] = $token->convertToJWT()->toString();
}
function http(string $path, ?string $bearer = NULL, string $method = 'GET', ?array $body = NULL,
  ?string $jar = NULL, ?string $csrf = NULL): array {
  $curl = curl_init('http://127.0.0.1:8080' . $path);
  $jsonapi = str_starts_with($path, '/jsonapi/');
  $type = $jsonapi ? 'application/vnd.api+json' : 'application/json';
  $headers = ['Accept: ' . $type, 'Content-Type: ' . $type];
  if ($csrf) {
    $headers[] = 'X-CSRF-Token: ' . $csrf;
  }
  if ($bearer) {
    $headers[] = 'Authorization: Bearer ' . $bearer;
  }
  $cache = '';
  curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => FALSE,
    CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$cache): int {
      if (str_starts_with(strtolower($line), 'cache-control:')) {
        $cache = $line;
      }
      return strlen($line);
    }]);
  if ($jar !== NULL) {
    curl_setopt($curl, CURLOPT_COOKIEFILE, $jar);
    curl_setopt($curl, CURLOPT_COOKIEJAR, $jar);
  }
  if ($body !== NULL) {
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
  }
  $content = curl_exec($curl);
  $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
  curl_close($curl);
  if ($status >= 500) {
    throw new RuntimeException('HTTP server failure: ' . substr((string) file_get_contents(
      '/tmp/analytics-test/reference-http.log'), -5000));
  }
  if ($jsonapi) {
    check(str_contains($cache, 'no-store') && str_contains($cache, 'private'), 'JSON:API no-store ' . $status);
  }
  check(!str_contains((string) $content, 'private-count'), 'Response contains no report body');
  return ['status' => $status, 'data' => json_decode((string) $content, TRUE)];
}
// The setup kernel disables container dumping; discard its predecessor before HTTP boot.
\Drupal::service('kernel')->invalidateContainer();
$server = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-S', '127.0.0.1:8080', __FILE__],
  [0 => ['file', '/dev/null', 'r'], 1 => ['file', $fixture . '/reference-http.log', 'a'],
    2 => ['file', $fixture . '/reference-http.log', 'a']], $pipes);
try {
  for ($i = 0; $i < 60; $i++) {
    if ($socket = @fsockopen('127.0.0.1', 8080, $errno, $error, 0.1)) {
      fclose($socket);
      break;
    }
    usleep(50000);
  }
  $base = '/jsonapi/node/ai_session';
  foreach (['owner', 'other', 'anonymous'] as $name) {
    $bearer = $bearers[$name] ?? NULL;
    $single = http($base . '/' . $runId, $bearer);
    check($single['status'] === ($name === 'owner' ? 200 : ($name === 'anonymous' ? 401 : 403)), 'Single access: ' . $name);
    $list = http($base, $bearer);
    check(!empty($list['data']['data']) === ($name === 'owner'), 'Collection access: ' . $name);
    $path = '/jsonapi/node/conversation/' . $conversation->uuid();
    $included = http($path . '?include=sessions', $bearer);
    check(!empty($included['data']['included']) === ($name === 'owner'), 'Include access: ' . $name);
    $related = http($path . '/sessions', $bearer);
    check(!empty($related['data']['data']) === ($name === 'owner'), 'Related resource access: ' . $name);
    $relationship = http($path . '/relationships/sessions', $bearer);
    check(!empty($relationship['data']['data']) === ($name === 'owner'), 'Relationship linkage access: ' . $name);
  }
  $bad = http($base . '/' . $runId, $bearers['owner'], 'PATCH', ['data' => [
    'type' => 'node--ai_session', 'id' => $runId, 'attributes' => ['summary' => 'private-count-123'],
  ]]);
  check($bad['status'] === 422, 'JSON:API rejects a body copy with a validation error');
  $bad = http($base . '/' . $runId, $bearers['owner'], 'PATCH', ['data' => [
    'type' => 'node--ai_session', 'id' => $runId, 'attributes' => ['content' => 'ordinary'],
  ]]);
  check($bad['status'] === 422, 'JSON:API cannot remove protection');
  $bad = http('/jsonapi/node/conversation/' . $otherConversation->uuid() . '/relationships/sessions',
    $bearers['owner'], 'POST', ['data' => [['type' => 'node--ai_session', 'id' => $runId]]]);
  check($bad['status'] === 422, 'JSON:API relationship writes cannot rebind a reference');
  $newId = \Drupal::service('uuid')->generate();
  $newRef = [...$ref, 'runId' => $newId];
  $created = http($base, $bearers['owner'], 'POST', ['data' => [
    'type' => 'node--ai_session', 'id' => $newId, 'attributes' => [
      'title' => 'Protected task result', 'session_role' => 'assistant', 'content' => json_encode($newRef),
    ],
  ]]);
  check($created['status'] === 201 && $created['data']['data']['attributes']['content'] === json_encode($newRef),
    'JSON:API creates a reference using the first-party session shape');
  $ordinary = http($base, $bearers['owner'], 'POST', ['data' => [
    'type' => 'node--ai_session', 'attributes' => [
      'title' => 'Ordinary answer', 'session_role' => 'assistant', 'content' => 'Explain xinshi-protected-run',
      'summary' => 'Ordinary summary', 'total_tokens' => 7,
    ],
  ]]);
  check($ordinary['status'] === 201, 'Optional module preserves ordinary session creation');
  $jar = $fixture . '/reference-owner.cookie';
  $login = http('/user/login?_format=json', NULL, 'POST', [
    'name' => 'reference-owner', 'pass' => 'test-only-reference',
  ], $jar);
  check($login['status'] === 200, 'Cookie owner logs into the disposable site');
  check(http($base . '/' . $runId, NULL, 'GET', NULL, $jar)['status'] === 200,
    'Cookie owner can read the locator');
  $patch = ['data' => ['type' => 'node--ai_session', 'id' => $runId, 'attributes' => ['summary' => '']]];
  check(http($base . '/' . $runId, NULL, 'PATCH', $patch, $jar)['status'] === 403,
    'Cookie writes retain core CSRF protection');
  check(http($base . '/' . $runId, NULL, 'PATCH', $patch, $jar, $login['data']['csrf_token'])['status'] === 200,
    'Cookie owner can save an unchanged locator with valid CSRF');
  echo 'Reference checks: ' . $checks . PHP_EOL;
}
finally {
  proc_terminate($server);
  proc_close($server);
}
