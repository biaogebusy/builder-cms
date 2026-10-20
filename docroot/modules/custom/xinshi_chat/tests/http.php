<?php

declare(strict_types=1);

/**
 * @file
 * Real OAuth requests against Chat installed without AI providers or metering.
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
if (getenv('XINSHI_CHAT_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  $root !== '/app' || !is_file('/tmp/chat-test/site.sqlite')) {
  throw new RuntimeException('Use the disposable Chat runner only.');
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
if (\Drupal::database()->getConnectionOptions()['database'] !== '/tmp/chat-test/site.sqlite') {
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
function http(string $path, ?string $token = NULL, string $method = 'GET', ?array $body = NULL,
  ?string $jar = NULL): array {
  $curl = curl_init('http://127.0.0.1:8080' . $path);
  $type = str_starts_with($path, '/jsonapi/') ? 'application/vnd.api+json' : 'application/json';
  $headers = ['Accept: ' . $type, 'Content-Type: ' . $type];
  if ($token !== NULL) {
    $headers[] = 'Authorization: Bearer ' . $token;
  }
  $cache = '';
  curl_setopt_array($curl, [CURLOPT_HTTPHEADER => $headers, CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => FALSE,
    CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$cache): int {
      if (str_starts_with(strtolower($line), 'cache-control:')) {
        $cache = strtolower($line);
      }
      return strlen($line);
    }]);
  if ($body !== NULL) {
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
  }
  if ($jar !== NULL) {
    curl_setopt($curl, CURLOPT_COOKIEJAR, $jar);
    curl_setopt($curl, CURLOPT_COOKIEFILE, $jar);
  }
  $body = curl_exec($curl);
  $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
  curl_close($curl);
  if ($body === FALSE || $status === 500) {
    throw new RuntimeException('HTTP failure: ' . file_get_contents('/tmp/chat-test/http.log'));
  }
  if (!str_starts_with($path, '/user/')) {
    check(str_contains($cache, 'private') && str_contains($cache, 'no-store'), 'Private no-store HTTP ' . $status);
  }
  return ['status' => $status, 'data' => json_decode($body, TRUE)];
}

// Reproduce a site's anonymous REST Views before checking the module's route precedence.
$test_module = $root . '/docroot/modules/custom/xinshi_chat_test';
mkdir($test_module);
file_put_contents($test_module . '/xinshi_chat_test.info.yml',
  "name: Chat access tests\ntype: module\npackage: Testing\ncore_version_requirement: ^11\n");
copy(__DIR__ . '/field-access.module', $test_module . '/xinshi_chat_test.module');
\Drupal::service('extension.list.module')->reset();
\Drupal::service('module_installer')->install(['views', 'rest', 'xinshi_chat_test']);
foreach (['conversations', 'sessions'] as $name) {
  \Drupal\views\Entity\View::create(['id' => 'old_' . $name, 'label' => 'Existing ' . $name,
    'base_table' => 'node_field_data', 'base_field' => 'nid', 'status' => TRUE,
    'display' => [
      'default' => ['id' => 'default', 'display_plugin' => 'default', 'display_options' => [
        'access' => ['type' => 'none'], 'cache' => ['type' => 'none'],
        'fields' => ['title' => ['id' => 'title', 'table' => 'node_field_data', 'field' => 'title', 'plugin_id' => 'field']],
      ]],
      'rest_export_1' => ['id' => 'rest_export_1', 'display_plugin' => 'rest_export', 'display_options' => [
        'path' => 'api/v2/' . $name, 'style' => ['type' => 'serializer'], 'row' => ['type' => 'data_field'],
        'defaults' => ['style' => FALSE, 'row' => FALSE],
      ]],
    ],
  ])->save();
}

Role::create(['id' => 'chat_admin', 'label' => 'Chat admin', 'permissions' => ['access content', 'bypass node access']])->save();
Role::create(['id' => 'chat_limited', 'label' => 'Limited', 'permissions' => []])->save();
foreach (['chat_owner', 'chat_admin', 'chat_limited'] as $role) {
  Oauth2Scope::create(['name' => $role, 'granularity_id' => 'role',
    'granularity_configuration' => ['role' => $role],
    'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
}
Consumer::create(['label' => 'Chat test', 'client_id' => 'chat-http'])->save();
$users = [];
foreach (['owner', 'other', 'admin', 'blocked'] as $name) {
  $users[$name] = User::create(['name' => 'chat-http-' . $name, 'pass' => 'test-only-chat',
    'status' => $name !== 'blocked', 'roles' => [$name === 'admin' ? 'chat_admin' : 'chat_owner', 'chat_limited']]);
  $users[$name]->save();
}
\Drupal::currentUser()->setAccount($users['owner']);
$message = Node::create(['type' => 'ai_session', 'title' => 'Question', 'uid' => $users['owner']->id(),
  'session_role' => 'user', 'storage_version' => 3, 'edit_version' => 1, 'storage_mode' => 'inline',
  'content' => ['value' => 'Original', 'format' => 'plain_text']]);
$message->save();
$chats = [];
for ($i = 0; $i < 25; $i++) {
  $chat = Node::create(['type' => 'conversation', 'title' => 'Chat fixture ' . $i,
    'uid' => $users['owner']->id(), 'model' => 'fixture', 'platform' => 'fixture',
    'chat_type' => $i === 24 ? 'plan' : 'chat', 'created' => 1792000000 + $i,
    'sessions' => $i === 24 ? [['target_id' => $message->id()]] : []]);
  $chat->save();
  $chats[] = $chat;
}
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $private);
file_put_contents('/tmp/chat-test/private.pem', $private);
file_put_contents('/tmp/chat-test/public.pem', openssl_pkey_get_details($key)['key']);
chmod('/tmp/chat-test/private.pem', 0600);
\Drupal::configFactory()->getEditable('simple_oauth.settings')->set('private_key', '/tmp/chat-test/private.pem')
  ->set('public_key', '/tmp/chat-test/public.pem')->save();
$repo = \Drupal::service('simple_oauth.repositories.access_token');
$client = \Drupal::service('simple_oauth.repositories.client')->getClientEntity('chat-http');
$tokens = [];
foreach (['owner', 'other', 'admin', 'blocked', 'limited'] as $name) {
  $role = $name === 'admin' ? 'chat_admin' : ($name === 'limited' ? 'chat_limited' : 'chat_owner');
  $scope = \Drupal::service('simple_oauth.repositories.scope')->getScopeEntityByIdentifier($role);
  $user = $users[$name === 'limited' ? 'owner' : $name];
  $token = $repo->getNewToken($client, $name === 'limited' ? [] : [$scope], (string) $user->id());
  $token->setIdentifier(bin2hex(random_bytes(24)));
  $token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
  $token->setPrivateKey(new CryptKey('/tmp/chat-test/private.pem'));
  $repo->persistNewAccessToken($token);
  $tokens[$name] = $token->convertToJWT()->toString();
}
\Drupal::service('router.builder')->rebuild();
foreach (['conversations', 'sessions'] as $name) {
  $routes = \Drupal::service('router.route_provider')->getRoutesByPattern('/api/v2/' . $name);
  check(array_keys($routes->all()) === ['xinshi_chat.' . $name], 'Module route replaces the old REST View: ' . $name);
  check(str_contains($routes->get('xinshi_chat.' . $name)->getDefault('_controller'),
    'xinshi_chat\\Controller\\ConversationListController'), 'Views cannot replace the module controller: ' . $name);
  check((bool) \Drupal\views\Entity\View::load('old_' . $name), 'Existing View configuration remains available: ' . $name);
}
\Drupal::service('kernel')->invalidateContainer();
$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:8080', __FILE__],
  [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/tmp/chat-test/http.log', 'a'],
    2 => ['file', '/tmp/chat-test/http.log', 'a']], $pipes);
try {
  for ($i = 0; $i < 60; $i++) {
    if ($socket = @fsockopen('127.0.0.1', 8080, $errno, $error, 0.1)) {
      fclose($socket);
      break;
    }
    usleep(50000);
  }
  $chat = $chats[24];
  $base = '/api/v3/ai/conversations/' . $chat->uuid();
  $message_path = $base . '/sessions/' . $message->uuid() . '/fields';
  $list = http('/api/v2/conversations', $tokens['owner']);
  check($list['status'] === 200 && count($list['data']['rows'] ?? []) === 20 &&
    $list['data']['pager'] === ['current_page' => 0, 'total_pages' => 2, 'total_items' => 25, 'items_per_page' => 20],
    'New site has the complete conversation list and pager contract');
  check(preg_match('/^\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}$/', $list['data']['rows'][0]['created']) === 1,
    'List dates match the existing frontend parser');
  check(count(http('/api/v2/conversations?page=1', $tokens['owner'])['data']['rows']) === 5, 'List page two is complete');
  check(count(http('/api/v2/conversations?keyword=fixture%2024&chat_type=plan', $tokens['owner'])['data']['rows']) === 1,
    'List keyword and type filters retain the first-party contract');
  \Drupal::state()->set('xinshi_chat_test.hidden_title', $chat->id());
  check(http('/api/v2/conversations', $tokens['owner'])['data']['pager']['total_items'] === 24,
    'An unreadable title is excluded from rows and counts');
  check(http('/api/v2/conversations?keyword=fixture%2024', $tokens['owner'])['data']['rows'] === [],
    'Keyword filtering cannot reveal a field-denied title');
  \Drupal::state()->delete('xinshi_chat_test.hidden_title');
  check(http('/api/v2/conversations?page=-1', $tokens['owner'])['status'] === 400, 'Invalid pagination fails explicitly');
  check(http('/api/v2/conversations', $tokens['other'])['data']['rows'] === [], 'List never returns another author');
  check(http('/api/v2/sessions', $tokens['owner'])['data']['rows'][0]['uuid'] === $message->uuid(),
    'The module provides the older session list without a manual View');
  check(http('/api/v2/conversations', $tokens['blocked'])['status'] === 401, 'Blocked OAuth account is rejected');
  check(http('/api/v2/conversations')['status'] === 403, 'Anonymous list access is denied');
  check(http('/api/v3/ai/chat-storage', $tokens['owner'])['data'] === ['storage_version' => 3], 'Chat publishes current storage version');
  $history = http($base . '/sessions', $tokens['owner']);
  check($history['status'] === 200 && $history['data']['messages'][0]['fields']['content']['value'] === 'Original',
    'Moved history controller reads the installed message structure');
  check(http($base . '/sessions', $tokens['other'])['status'] === 403, 'History denies another owner');
  check(http($base . '/sessions', $tokens['limited'])['status'] === 403, 'OAuth scopes retain entity permission restrictions');
  check(http($base . '/sessions', $tokens['admin'])['status'] === 200, 'Existing administrator preview remains available');
  check(http('/jsonapi/node/ai_session/' . $message->uuid(), $tokens['other'])['status'] === 200,
    'Ordinary JSON:API retains the existing site permission for shared previews');
  check(http('/jsonapi/node/conversation/' . $chat->uuid() . '/relationships/sessions', $tokens['other'])['status'] === 200,
    'Ordinary relationship reads retain existing entity and field permissions');
  $patch = ['expected_edit_version' => 1, 'fields' => ['summary' => 'Updated summary']];
  check(http($message_path, $tokens['owner'], 'PATCH', $patch)['status'] === 200, 'Moved multi-field CAS route updates an owned message');
  check(http($message_path, $tokens['other'], 'PATCH', $patch)['status'] === 403, 'Moved CAS still rejects another owner');
  check(http($message_path, $tokens['owner'], 'PATCH', ['expected_edit_version' => 1,
    'fields' => ['summary' => 'Stale']])['status'] === 409, 'Moved CAS retains conflict detection');
  check(http($base, $tokens['owner'], 'POST', ['sessions' => [], 'attributes' => ['title' => 'Renamed']])['status'] === 200,
    'Moved conversation writer retains its update contract');
  $web_choice = ['sessions' => [], 'attributes' => ['web_search' => TRUE]];
  check(http($base, $tokens['owner'], 'POST', $web_choice)['status'] === 200,
    'OAuth owner can enable conversation web search');
  check(http('/jsonapi/node/conversation/' . $chat->uuid(), $tokens['owner'])['data']['data']['attributes']['web_search'] === TRUE,
    'JSON:API returns the saved Boolean web preference');
  check(http($base, $tokens['other'], 'POST', $web_choice)['status'] === 403,
    'Another author cannot change the conversation web preference');
  check(http($base, $tokens['owner'], 'POST', ['sessions' => [], 'attributes' => ['web_search' => 'true']])['status'] === 422,
    'Conversation web preference rejects string coercion');
  $jar = '/tmp/chat-test/owner.cookie';
  check(http('/user/login?_format=json', NULL, 'POST', ['name' => 'chat-http-owner', 'pass' => 'test-only-chat'], $jar)['status'] === 200,
    'The synthetic owner can create a browser session');
  check(http($message_path, NULL, 'PATCH', $patch, $jar)['status'] === 403, 'Chat mutation routes remain OAuth-only');
  \Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('enabled', FALSE)->save();
  foreach (['/api/v2/conversations', $base . '/sessions', '/jsonapi/node/ai_session/' . $message->uuid()] as $path) {
    check(http($path, $tokens['owner'])['status'] === 503, 'Disabled Chat stops ' . $path);
  }
  \Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('enabled', TRUE)->save();
  check(http($message_path, $tokens['owner'])['data']['session']['summary'] === 'Updated summary',
    'Re-enabling Chat preserves saved messages');
  echo 'Chat HTTP checks passed: ' . $checks . PHP_EOL;
}
finally {
  proc_terminate($server);
  proc_close($server);
}
