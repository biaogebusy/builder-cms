<?php

declare(strict_types=1);

/**
 * @file
 * Tests real Drupal storage, access checks and the production metadata route on synthetic data.
 */

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_ai\Controller\AiLogMetadataController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

if (getenv('XINSHI_CHAT_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  dirname(__DIR__, 6) !== '/app' || file_exists('/tmp/chat-test')) {
  throw new RuntimeException('Use a fresh disposable source-copy container.');
}
$completed = FALSE;
register_shutdown_function(static function () use (&$completed): void {
  if (!$completed) {
    exit(1);
  }
});
$loader = require '/app/vendor/autoload.php';
$module = dirname(__DIR__, 2);
$loader->addPsr4('Drupal\\xinshi_ai\\', $module . '/src');
$testModule = '/app/docroot/modules/custom/ai_log_metadata_test';
mkdir($testModule);
file_put_contents($testModule . '/ai_log_metadata_test.info.yml', Yaml::dump([
  'name' => 'AI log metadata test', 'type' => 'module', 'core_version_requirement' => '^11',
  'dependencies' => ['xinshi_chat', 'jsonapi_extras'],
]));
foreach (['routing', 'services', 'permissions'] as $kind) {
  $resourceConfig = Yaml::parseFile($module . '/xinshi_ai.' . $kind . '.yml');
  $selected = match ($kind) {
    'routing' => ['xinshi_ai.log_metadata' => $resourceConfig['xinshi_ai.log_metadata']],
    'services' => ['services' => ['xinshi_ai.log_metadata' => $resourceConfig['services']['xinshi_ai.log_metadata']]],
    'permissions' => ['administer xinshi_ai' => $resourceConfig['administer xinshi_ai']],
  };
  file_put_contents($testModule . '/ai_log_metadata_test.' . $kind . '.yml', Yaml::dump($selected, 8));
}
copy(__DIR__ . '/access.module', $testModule . '/ai_log_metadata_test.module');
require dirname(__DIR__, 3) . '/xinshi_chat/tests/install-fixture.php';
\Drupal::service('module_installer')->install(['ai_log_metadata_test']);
$checks = 0;
function check(bool $result, string $message): void {
  if (!$result) {
    throw new RuntimeException($message);
  }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function response(array $query = []): \Symfony\Component\HttpFoundation\JsonResponse {
  return AiLogMetadataController::create(\Drupal::getContainer())->read(
    Request::create('/api/v3/ai/log-metadata', 'GET', $query));
}
function metadata(array $query): array {
  $response = response($query);
  check($response->getStatusCode() === 200, 'Metadata read succeeds');
  check($response->headers->hasCacheControlDirective('no-store'), 'Metadata cannot be cached');
  return json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
}
function resetAccess(): void {
  foreach (['node', 'user'] as $type) {
    \Drupal::entityTypeManager()->getAccessControlHandler($type)->resetCache();
    \Drupal::entityTypeManager()->getStorage($type)->resetCache();
  }
}
Role::create(['id' => 'log_admin', 'label' => 'Log administrator',
  'permissions' => ['administer xinshi_ai', 'access content']])->save();
$admin = User::create(['name' => 'Log administrator', 'status' => 1, 'roles' => ['log_admin']]);
$admin->save();
$author = User::create(['name' => 'Another author', 'status' => 1]);
$author->save();
$chat = Node::create(['type' => 'conversation', 'title' => 'Current conversation title',
  'uid' => $author->id(), 'status' => 1, 'chat_type' => 'fullHTML']);
$chat->save();
$query = ['chatIds' => $chat->uuid(), 'userIds' => $admin->id() . ',' . $author->id()];
\Drupal::currentUser()->setAccount($admin);
check(\Drupal::service('access_manager')->checkNamedRoute('xinshi_ai.log_metadata', [], $admin),
  'The real route permits the explicit AI administrator permission');
$result = metadata($query);
check($result['conversations'] === [['id' => $chat->uuid(), 'title' => 'Current conversation title',
  'type' => 'fullHTML']], 'Reads another author conversation without sessions or message bodies');
check(count($result['users']) === 2 && array_keys($result['users'][0]) === ['uid', 'name'],
  'User batches contain only UID and display name');
$chat->setTitle('Renamed in Drupal')->save();
$author->setUsername('Renamed author')->save();
$result = metadata($query);
check($result['conversations'][0]['title'] === 'Renamed in Drupal' &&
  $result['users'][1]['name'] === 'Renamed author', 'Reads current labels after edits');
check(metadata(['chatIds' => '00000000-0000-4000-8000-000000000000', 'userIds' => '2147483647']) ===
  ['conversations' => [], 'users' => []], 'Missing identities are omitted');
foreach (['title', 'chat_type'] as $field) {
  \Drupal::state()->set('ai_log_metadata_test.field', $field);
  resetAccess();
  $rows = metadata($query)['conversations'];
  check($field === 'title' ? $rows === [] : !isset($rows[0]['type']), 'Honors field denial: ' . $field);
}
\Drupal::state()->delete('ai_log_metadata_test.field');
foreach ([$chat, $author] as $entity) {
  \Drupal::state()->set('ai_log_metadata_test.entity', $entity->uuid());
  resetAccess();
  $result = metadata($query);
  check($entity === $chat ? $result['conversations'] === [] : count($result['users']) === 1,
    'Honors entity access denial: ' . $entity->getEntityTypeId());
}
\Drupal::state()->delete('ai_log_metadata_test.entity');
resetAccess();
$resourceConfig = JsonapiResourceConfig::create(['id' => 'node--conversation', 'disabled' => FALSE,
  'path' => 'node/conversation', 'resourceType' => 'node--conversation',
  'resourceFields' => ['chat_type' => ['fieldName' => 'chat_type', 'publicName' => 'kind', 'disabled' => FALSE]]]);
$resourceConfig->save();
check(metadata($query)['conversations'][0]['type'] === 'fullHTML',
  'A renamed public field preserves the enabled source field value');
$resourceConfig->set('resourceFields', ['title' => ['fieldName' => 'title', 'publicName' => 'title', 'disabled' => TRUE]])->save();
check(metadata($query)['conversations'] === [], 'Disabled title suppresses conversation metadata');
$resourceConfig->set('resourceFields', [])->set('disabled', TRUE)->save();
check(metadata($query)['conversations'] === [], 'Disabled resources suppress conversation metadata');
$resourceConfig->delete();
foreach ([['userIds' => ['1']], ['userIds' => '01'], ['userIds' => '2147483648'],
  ['chatIds' => 'invalid'], ['userIds' => implode(',', array_fill(0, 101, '1'))], ['include' => 'sessions']] as $invalid) {
  check(response($invalid)->getStatusCode() === 400, 'Rejects invalid or unbounded metadata query');
}
foreach ([$author, new AnonymousUserSession()] as $account) {
  \Drupal::currentUser()->setAccount($account);
  check(!\Drupal::service('access_manager')->checkNamedRoute('xinshi_ai.log_metadata', [], $account),
    'Route denies an unauthorized principal');
  check(response($query)->getStatusCode() === 403, 'The service also denies unauthorized direct calls');
}
$completed = TRUE;
echo 'AI log metadata: ' . $checks . ' checks passed on disposable Drupal.' . PHP_EOL;
