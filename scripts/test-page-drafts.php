<?php

/**
 * @file
 * Real Drupal HTTP/database regression checks using disposable fixture accounts.
 * Run with drush php:script; see the maintained AI harness operations guide.
 */

use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;

final class PageDraftRegression {

  public array $state;
  private Client $http;
  private bool $ownsState = FALSE;

  public function __construct(private string $stateFile, string $baseUrl) {
    if (!$baseUrl || !in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], TRUE)) {
      throw new RuntimeException('Set XINSHI_DRAFT_TEST_BASE_URL to the development Drupal origin.');
    }
    $this->http = new Client([
      'base_uri' => rtrim($baseUrl, '/') . '/', 'http_errors' => FALSE,
      'allow_redirects' => FALSE, 'timeout' => 30,
    ]);
    $this->state = ['tag' => 'h55_' . bin2hex(random_bytes(6)), 'users' => [],
      'roles' => [], 'scopes' => [], 'clients' => [], 'tokens' => []];
  }

  public function setup(): void {
    if (file_exists($this->stateFile)) {
      throw new RuntimeException('Test state already exists; clean up that fixture first.');
    }
    $tag = $this->state['tag'];
    $permissions = ['access content', 'access user profiles', 'create landing_page content',
      'view own unpublished content', 'create json block content', 'access block library',
      'use text format json', 'restful get xinshi_api_landing_page_rest'];
    $probe = \Drupal::entityTypeManager()->getStorage('node')->create(['type' => 'landing_page']);
    if (\Drupal::hasService('content_moderation.moderation_information')) {
      $workflow = \Drupal::service('content_moderation.moderation_information')
        ->getWorkflowForEntity($probe);
      if ($workflow) {
        foreach ($workflow->getTypePlugin()->getTransitions() as $transition) {
          if ($transition->to()->id() === 'draft') {
            $permissions[] = 'use ' . $workflow->id() . ' transition ' . $transition->id();
          }
        }
      }
    }
    foreach (['editor' => $permissions, 'reader' => ['access content']] as $kind => $grants) {
      $role = Role::create(['id' => $tag . '_' . $kind, 'label' => $tag . ' ' . $kind,
        'permissions' => $grants]);
      $role->save();
      $this->state['roles'][$kind] = $role->id();
      $scope = Oauth2Scope::create([
        'name' => $tag . ':' . $kind, 'description' => 'Disposable page draft regression scope',
        'grant_types' => ['client_credentials' => ['status' => TRUE, 'description' => 'Test']],
        'umbrella' => FALSE, 'granularity_id' => Oauth2ScopeInterface::GRANULARITY_ROLE,
        'granularity_configuration' => ['role' => $role->id()],
      ]);
      $scope->save();
      $this->state['scopes'][$kind] = $scope->id();
    }
    // Explicit scopes let revocation tests remove even permissions inherited from authenticated.
    $permissionScopes = [];
    foreach (array_values(array_unique($permissions)) as $index => $permission) {
      $name = $tag . ':permission:' . $index;
      $scope = Oauth2Scope::create([
        'name' => $name, 'description' => 'Disposable page draft permission scope',
        'grant_types' => ['client_credentials' => ['status' => TRUE, 'description' => 'Test']],
        'umbrella' => FALSE, 'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
        'granularity_configuration' => ['permission' => $permission],
      ]);
      $scope->save();
      $this->state['scopes']['permission_' . $index] = $scope->id();
      $this->state['permissionScopes'][$permission] = $scope->id();
      $permissionScopes[] = $name;
    }
    for ($index = 0; $index < 2; $index++) {
      $password = bin2hex(random_bytes(20));
      $user = User::create(['name' => $tag . '_' . $index, 'status' => 1,
        'mail' => $tag . '_' . $index . '@example.invalid', 'pass' => $password,
        'roles' => [$this->state['roles']['editor'], $this->state['roles']['reader']]]);
      $user->save();
      $this->state['users'][] = (string) $user->id();
      $secret = bin2hex(random_bytes(20));
      $client = Consumer::create([
        'client_id' => $tag . '_' . $index, 'label' => $tag . ' client ' . $index,
        'secret' => $secret, 'confidential' => TRUE, 'user_id' => $user->id(),
        'access_token_expiration' => 3600,
        'grant_types' => ['client_credentials'], 'scopes' => array_values($this->state['scopes']),
      ]);
      $client->save();
      $this->state['clients'][] = $client->id();
      foreach (['editor' => $tag . ':editor', 'reader' => $tag . ':reader',
        'scoped' => implode(' ', $permissionScopes)] as $kind => $scopeNames) {
        $response = $this->http->post('oauth/token', ['form_params' => [
          'grant_type' => 'client_credentials', 'client_id' => $client->getClientId(),
          'client_secret' => $secret, 'scope' => $scopeNames,
        ]]);
        $token = json_decode((string) $response->getBody(), TRUE);
        $this->check($response->getStatusCode() === 200 && !empty($token['access_token']),
          'OAuth fixture token issued');
        $this->state['tokens'][$index][$kind] = $token['access_token'];
      }
    }
    $this->saveState();
  }

  public function load(): void {
    $this->state = json_decode(file_get_contents($this->stateFile), TRUE, 64, JSON_THROW_ON_ERROR);
    if (!preg_match('/^h55_[a-f0-9]{12}$/D', $this->state['tag'] ?? '')) {
      throw new RuntimeException('Invalid regression fixture state.');
    }
    $this->ownsState = TRUE;
  }

  public function cleanup(): void {
    $manager = \Drupal::entityTypeManager();
    $tag = $this->state['tag'];
    foreach ($this->state['users'] as $uid) {
      $user = $manager->getStorage('user')->load($uid);
      if (!$user || !str_starts_with($user->getAccountName(), $tag . '_')) {
        throw new RuntimeException('Fixture identity changed; refusing cleanup.');
      }
      $nodes = $manager->getStorage('node')->loadByProperties(['uid' => $uid]);
      $manager->getStorage('node')->delete($nodes);
      \Drupal::database()->delete('xinshi_page_draft_operation')->condition('uid', $uid)->execute();
    }
    $blocks = $manager->getStorage('block_content')->getQuery()->accessCheck(FALSE)
      ->condition('info', $tag . '%', 'LIKE')->execute();
    $manager->getStorage('block_content')->delete($manager->getStorage('block_content')->loadMultiple($blocks));
    foreach ($this->state['clients'] as $id) {
      $tokens = $manager->getStorage('oauth2_token')->loadByProperties(['client' => $id]);
      $manager->getStorage('oauth2_token')->delete($tokens);
      $manager->getStorage('consumer')->load($id)?->delete();
    }
    foreach ($this->state['users'] as $uid) {
      $manager->getStorage('user')->load($uid)?->delete();
    }
    foreach ($this->state['scopes'] as $id) {
      $manager->getStorage('oauth2_scope')->load($id)?->delete();
    }
    foreach ($this->state['roles'] as $id) {
      $manager->getStorage('user_role')->load($id)?->delete();
    }
    if ($this->ownsState && is_file($this->stateFile)) {
      unlink($this->stateFile);
    }
    print "Disposable fixtures cleaned up.\n";
  }

  private function saveState(): void {
    $mask = umask(0077);
    file_put_contents($this->stateFile, json_encode($this->state, JSON_THROW_ON_ERROR));
    $this->ownsState = TRUE;
    umask($mask);
  }

  private function request(string $method, string $path, ?array $input = NULL,
    ?string $token = NULL): array {
    $options = ['headers' => ['Accept' => str_starts_with($path, 'api/v1/block_content/')
      ? 'application/vnd.api+json' : 'application/json']];
    if ($token !== NULL) {
      $options['headers']['Authorization'] = 'Bearer ' . $token;
    }
    if ($input !== NULL) {
      $options['json'] = $input;
    }
    $response = $this->http->request($method, ltrim($path, '/'), $options);
    return [$response->getStatusCode(), json_decode((string) $response->getBody(), TRUE), $response];
  }

  private function check(bool $condition, string $label): void {
    if (!$condition) {
      throw new RuntimeException('FAIL: ' . $label);
    }
    print 'PASS: ' . $label . PHP_EOL;
  }

  public function run(): void {
    $token = $this->state['tokens'][0]['editor'];
    $reader = $this->state['tokens'][0]['reader'];
    $other = $this->state['tokens'][1]['editor'];
    $input = ['title' => $this->state['tag'] . ' 页面',
      'body' => [
        ['spacer' => 'md', 'type' => 'text', 'body' => '测试成功', 'animate' => FALSE],
        ['spacer' => 'md', 'type' => 'text', 'body' => '第二个组件', 'animate' => FALSE],
      ]];
    $prefix = 'api/v3/landingPage/drafts/';
    [$status, $capabilities] = $this->request('GET', $prefix . 'capabilities', NULL, $token);
    $this->check($status === 200 && in_array('pages.create_draft', $capabilities['permissions'], TRUE),
      'Actual entity, format and moderation permissions allow draft creation');
    [, $limited] = $this->request('GET', $prefix . 'capabilities', NULL, $reader);
    $this->check($limited['permissions'] === [], 'OAuth scopes cannot borrow full account privileges');
    $uuid = \Drupal::service('uuid');
    $deniedId = $uuid->generate();
    [$status, $denied] = $this->request('POST', $prefix . $deniedId, $input, $reader);
    $this->check($status === 403 && $denied['outcome'] === 'not_written', 'Unauthorized POST refused');
    $this->check(!$this->operationExists($deniedId), 'Refusal leaves no operation reservation');

    $id = $uuid->generate();
    [$status, $saved] = $this->request('POST', $prefix . $id, $input, $token);
    $this->check($status === 200 && ($saved['result']['status'] ?? '') === 'draft',
      'Draft POST succeeded (HTTP ' . $status . ')');
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($saved['result']['id']);
    $this->check(!$node->isPublished() && (string) $node->getOwnerId() === $this->state['users'][0],
      'Page is unpublished and owned by the authenticated user');
    $display = $node->get('panelizer')->first()->panels_display;
    $configs = array_values($display['blocks']);
    $this->check(count($configs) === count($input['body']),
      'Each approved top-level component has its own JSON block');
    foreach ($configs as $index => $config) {
      $block = \Drupal::entityTypeManager()->getStorage('block_content')->loadRevision($config['vid']);
      $this->check($block && !$block->isPublished() &&
        json_decode($block->get('body')->value, TRUE) === $input['body'][$index],
        'Stored draft block preserves one approved component object at index ' . $index);
    }
    $this->check(array_column($configs, 'weight') === array_keys($input['body']),
      'Panels preserves the approved component order');
    [$status, $canonical] = $this->request('GET', 'api/v3/landingPage/json/' . $node->id(), NULL, $token);
    $this->check($status === 200 && ($canonical['status'] ?? FALSE) &&
      array_column(array_column($canonical['body'], 'attributes'), 'body') === $input['body'],
      'Builder page JSON returns the approved components without an extra array layer');
    $this->check(count(array_unique(array_column($canonical['body'], 'uuid'))) === count($input['body']),
      'Builder receives a separate block identity for every component');
    [$status] = $this->request('GET', 'api/v3/landingPage/json/' . $node->id());
    $this->check($status === 403, 'Anonymous canonical JSON cannot expose the draft');
    [$status, $blockResponse] = $this->request('GET', 'api/v1/block_content/json/' . $block->uuid());
    $this->check(in_array($status, [401, 403, 404], TRUE) &&
      !str_contains(json_encode($blockResponse), $this->state['tag']),
      'Anonymous JSON:API cannot expose the draft block (HTTP ' . $status . ')');
    $public_path = 'api/v3/landingPage?_format=json&content=/node/' . $node->id();
    [$status, $owner_page, $owner_response] = $this->request('GET', $public_path, NULL, $token);
    $this->check($status === 200 && str_contains(json_encode($owner_page), $this->state['tag']),
      'Owner can read the draft through the native landing page REST resource');
    $cache_control = $owner_response->getHeaderLine('Cache-Control');
    $this->check(str_contains($cache_control, 'no-cache') || str_contains($cache_control, 'max-age=0'),
      'Draft REST response cannot be cached across owners');
    [, $other_page] = $this->request('GET', $public_path, NULL, $other);
    $this->check(!str_contains(json_encode($other_page), $this->state['tag']),
      'Another user with the same role cannot receive a cached draft response');
    [, $public] = $this->request('GET', $public_path);
    $this->check(!str_contains(json_encode($public), $this->state['tag']),
      'Public page lookup cannot expose the draft');
    [$status] = $this->request('GET', $prefix . $id, NULL, $other);
    $this->check($status === 404, 'Another user cannot inspect the operation');
    [$status, $found] = $this->request('GET', $prefix . $id, NULL, $token);
    // JSON object property order is not significant after the stored input is canonicalized.
    $this->check($status === 200 && $found == $saved, 'GET recovers the immutable operation result');
    [$status, $again] = $this->request('POST', $prefix . $id,
      ['body' => $input['body'], 'title' => $input['title']], $token);
    $this->check($status === 200 && $again == $saved, 'Duplicate POST with reordered keys reuses the result');
    [$status] = $this->request('POST', $prefix . $id, array_replace($input, ['title' => 'Changed']), $token);
    $this->check($status === 409, 'Operation ID cannot be reused with changed input');

    $concurrentId = $uuid->generate();
    $requests = [];
    for ($index = 0; $index < 2; $index++) {
      $requests[] = $this->http->postAsync($prefix . $concurrentId, [
        'headers' => ['Authorization' => 'Bearer ' . $token], 'json' => $input,
      ]);
    }
    $responses = Utils::unwrap($requests);
    $values = array_map(fn($response) => json_decode((string) $response->getBody(), TRUE), $responses);
    $this->check($responses[0]->getStatusCode() === 200 && $responses[1]->getStatusCode() === 200 &&
      $values[0] == $values[1], 'Concurrent POSTs return one committed page');
    $this->check(count(\Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'uid' => $this->state['users'][0], 'title' => $input['title'],
    ])) === 2, 'Only the two distinct operation IDs created pages');

    foreach (['uid' => 1, 'status' => TRUE, 'executionId' => $id, 'langcode' => 'not-installed'] as $key => $value) {
      $invalidId = $uuid->generate();
      [$status] = $this->request('POST', $prefix . $invalidId, array_replace($input, [$key => $value]), $token);
      $this->check($status === 422 && !$this->operationExists($invalidId), 'Invalid ' . $key . ' never writes');
    }
    $role = Role::load($this->state['roles']['editor']);
    $scoped = $this->state['tokens'][0]['scoped'];
    [$status, $capabilities] = $this->request('GET', $prefix . 'capabilities', NULL, $scoped);
    $this->check($status === 200 && in_array('pages.create_draft', $capabilities['permissions'], TRUE),
      'Explicit OAuth permission scopes allow the baseline draft operation');
    foreach (['create landing_page content', 'create json block content', 'use text format json'] as $permission) {
      $scope = Oauth2Scope::load($this->state['permissionScopes'][$permission]);
      try {
        $role->revokePermission($permission)->save();
        $scope->getGranularity()->setConfiguration(['permission' => 'access content']);
        $scope->save();
        [$status, $capabilities] = $this->request('GET', $prefix . 'capabilities', NULL, $scoped);
        $this->check($status === 200 && !in_array('pages.create_draft', $capabilities['permissions'], TRUE),
          'Revoked ' . $permission . ' removes the advertised capability');
        $revokedId = $uuid->generate();
        [$status, $refusal] = $this->request('POST', $prefix . $revokedId, $input, $scoped);
        $this->check($status === 403 && $refusal['outcome'] === 'not_written' &&
          !$this->operationExists($revokedId), 'Revoked ' . $permission . ' blocks writes (HTTP ' . $status . ')');
        [$status, $found] = $this->request('GET', $prefix . $id, NULL, $scoped);
        $this->check($status === 200 && $found == $saved, 'Revocation preserves read-only recovery');
      }
      finally {
        $role->grantPermission($permission)->save();
        $scope->getGranularity()->setConfiguration(['permission' => $permission]);
        $scope->save();
      }
    }
    $this->rollback($input);
    $this->changes($input);

    $cookies = new \GuzzleHttp\Cookie\CookieJar();
    // Use Drupal's core one-time login to isolate CSRF checks from site login redirects.
    $account = User::load($this->state['users'][0]);
    $timestamp = \Drupal::time()->getCurrentTime();
    $loginPath = \Drupal\Core\Url::fromRoute('user.reset.login', [
      'uid' => $account->id(), 'timestamp' => $timestamp,
      'hash' => user_pass_rehash($account, $timestamp),
    ])->toString();
    $this->http->get(ltrim($loginPath, '/'), ['cookies' => $cookies]);
    $session = $this->http->get($prefix . 'capabilities', ['cookies' => $cookies]);
    $sessionData = json_decode((string) $session->getBody(), TRUE);
    $this->check($session->getStatusCode() === 200 &&
      ($sessionData['uid'] ?? NULL) === $this->state['users'][0] &&
      in_array('pages.create_draft', $sessionData['permissions'] ?? [], TRUE),
      'Cookie fixture is authenticated with draft creation permission');
    $csrfId = $uuid->generate();
    $csrf = $this->http->post($prefix . $csrfId, ['cookies' => $cookies, 'json' => $input]);
    $this->check($csrf->getStatusCode() === 403 && !$this->operationExists($csrfId),
      'Cookie POST requires a CSRF token');
    $csrfToken = $this->http->get('session/token', ['cookies' => $cookies]);
    $withCsrf = $this->http->post($prefix . $csrfId, [
      'cookies' => $cookies, 'json' => $input,
      'headers' => ['X-CSRF-Token' => (string) $csrfToken->getBody()],
    ]);
    $this->check($withCsrf->getStatusCode() === 200 && $this->operationExists($csrfId),
      'Cookie POST with a valid CSRF token creates the draft');
  }

  private function operationExists(string $id): bool {
    return (bool) \Drupal::database()->select('xinshi_page_draft_operation', 'op')->fields('op', ['execution_id'])
      ->condition('execution_id', $id)->execute()->fetchField();
  }

  private function changes(array $content): void {
    $token = $this->state['tokens'][0]['editor'];
    $other = $this->state['tokens'][1]['editor'];
    $prefix = 'api/v3/landingPage/drafts/';
    $uuid = \Drupal::service('uuid');
    $content['title'] .= ' change regression';
    [$status, $created] = $this->request('POST', $prefix . $uuid->generate(), $content, $token);
    $this->check($status === 200, 'Disposable draft for append/delete created');
    $pageId = $created['result']['id'];
    [$status, $page] = $this->request('GET', $prefix . 'page/' . $pageId, NULL, $token);
    $this->check($status === 200 && $page['body'] === $content['body'] &&
      preg_match('/^[a-f0-9]{64}$/D', $page['version']), 'Read returns current draft content and version');
    [$status] = $this->request('GET', $prefix . 'page/' . $pageId, NULL, $other);
    $this->check($status === 403, 'Another author cannot read the private draft');
    $input = ['action' => 'append', 'pageId' => $pageId, 'expectedVersion' => $page['version'],
      'body' => [['type' => 'btn', 'label' => '了解更多', 'href' => '/node/1', 'mode' => 'raised']]];
    [$status, $refused] = $this->request('POST', $prefix . $uuid->generate() . '/change', $input, $other);
    $this->check($status === 403 && $refused['outcome'] === 'not_written', 'Another author cannot append');
    $appendId = $uuid->generate();
    [$status, $saved] = $this->request('POST', $prefix . $appendId . '/change', $input, $token);
    $this->check($status === 200 && $saved['result']['id'] === $pageId &&
      $saved['result']['status'] === 'draft', 'Append preserves the same unpublished page ID');
    [, $after] = $this->request('GET', $prefix . 'page/' . $pageId, NULL, $token);
    $expected = [...$content['body'], ...$input['body']];
    $this->check($after['body'] === $expected && $after['version'] !== $page['version'],
      'Append preserves existing components and changes the version');
    [$status, $canonical] = $this->request('GET', 'api/v3/landingPage/json/' . $pageId, NULL, $token);
    $this->check($status === 200 && array_column(array_column($canonical['body'], 'attributes'), 'body') === $expected,
      'Builder page JSON renders original text followed by the button');
    [$status, $again] = $this->request('POST', $prefix . $appendId . '/change', $input, $token);
    $this->check($status === 200 && $again == $saved, 'Duplicate append reuses the immutable receipt');
    $staleId = $uuid->generate();
    [$status, $refused] = $this->request('POST', $prefix . $staleId . '/change', $input, $token);
    $this->check($status === 409 && $refused['code'] === 'version_conflict' &&
      $refused['outcome'] === 'not_written' && !$this->operationExists($staleId),
      'A new operation cannot reuse an outdated page version');

    // Different operation IDs still serialize by page, not just by the receipt's unique key.
    $input['expectedVersion'] = $after['version'];
    $requests = [];
    for ($index = 0; $index < 2; $index++) {
      $requests[] = $this->http->postAsync($prefix . $uuid->generate() . '/change', [
        'headers' => ['Authorization' => 'Bearer ' . $token], 'json' => $input,
      ]);
    }
    $responses = Utils::unwrap($requests);
    $statuses = array_map(fn($response) => $response->getStatusCode(), $responses);
    sort($statuses);
    $this->check($statuses === [200, 409], 'Concurrent appends against one version write only once');
    [, $after] = $this->request('GET', $prefix . 'page/' . $pageId, NULL, $token);
    $this->check(count($after['body']) === count($expected) + 1, 'No concurrent duplicate component');

    $deletion = ['action' => 'delete', 'pageId' => $pageId, 'expectedVersion' => $after['version']];
    [$status, $refused] = $this->request('POST', $prefix . $uuid->generate() . '/change', $deletion, $other);
    $this->check($status === 403 && $refused['outcome'] === 'not_written', 'Another author cannot delete');
    $deleteId = $uuid->generate();
    [$status, $deleted] = $this->request('POST', $prefix . $deleteId . '/change', $deletion, $token);
    $this->check($status === 200 && $deleted['result'] === ['id' => $pageId, 'status' => 'deleted'],
      'Owner deletes the approved draft');
    [$status] = $this->request('GET', $prefix . 'page/' . $pageId, NULL, $token);
    $this->check($status === 404, 'Deleted page is gone');
    [$status, $found] = $this->request('GET', $prefix . $deleteId, NULL, $token);
    $this->check($status === 200 && $found == $deleted, 'Deletion receipt remains recoverable');
    [$status, $again] = $this->request('POST', $prefix . $deleteId . '/change', $deletion, $token);
    $this->check($status === 200 && $again == $deleted, 'Duplicate deletion is idempotent');
    [$status] = $this->request('GET', $prefix . $deleteId, NULL, $other);
    $this->check($status === 404, 'Deletion receipt remains private to its actor');
  }

  private function rollback(array $input): void {
    $panelizer = new class(\Drupal::service('panelizer')) extends \Drupal\panelizer\Panelizer {
      public function __construct(private \Drupal\panelizer\PanelizerInterface $actual) {}
      public function getPanelizerSettings($entity_type_id, $bundle, $view_mode,
        \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display = NULL) {
        return $this->actual->getPanelizerSettings($entity_type_id, $bundle, $view_mode, $display);
      }
      public function setPanelsDisplay(\Drupal\Core\Entity\FieldableEntityInterface $entity,
        $view_mode, $default, \Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant $panels_display = NULL) {
        throw new RuntimeException('Injected failure after block save');
      }
    };
    $drafts = new \Drupal\xinshi_api\PageDraftService(
      \Drupal::database(), \Drupal::entityTypeManager(), \Drupal::currentUser(),
      \Drupal::languageManager(), \Drupal::time(), $panelizer,
      \Drupal::service('panels.display_manager'),
      \Drupal::service('content_moderation.moderation_information'),
      \Drupal::service('content_moderation.state_transition_validation')
    );
    $switcher = \Drupal::service('account_switcher');
    $switcher->switchTo(User::load($this->state['users'][0]));
    $input['title'] .= ' rollback';
    $id = \Drupal::service('uuid')->generate();
    try {
      $drafts->createDraft($id, json_decode(json_encode($input)));
      throw new RuntimeException('Expected an injected storage failure');
    }
    catch (RuntimeException $e) {
      $this->check($e->getMessage() === 'Injected failure after block save', 'Storage failure was injected');
      $blocks = \Drupal::entityTypeManager()->getStorage('block_content')
        ->loadByProperties(['info' => $input['title']]);
      $this->check(!$blocks && !$this->operationExists($id), 'Partial block and operation reservation roll back together');
    }
    finally {
      $switcher->switchBack();
    }
  }
}

$mode = getenv('XINSHI_DRAFT_TEST_MODE') ?: 'test';
$test = new PageDraftRegression(
  getenv('XINSHI_DRAFT_TEST_STATE') ?: sys_get_temp_dir() . '/xinshi-page-draft-test.json',
  getenv('XINSHI_DRAFT_TEST_BASE_URL') ?: ''
);
if ($mode === 'cleanup') {
  $test->load();
  $test->cleanup();
}
elseif ($mode === 'prepare') {
  try {
    $test->setup();
    print "Fixture prepared; run cleanup after the Node integration check.\n";
  }
  catch (Throwable $e) {
    $test->cleanup();
    throw $e;
  }
}
elseif ($mode === 'test') {
  try {
    $test->setup();
    $test->run();
  }
  finally {
    $test->cleanup();
  }
}
else {
  throw new RuntimeException('Unknown draft test mode.');
}
