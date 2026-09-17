<?php

use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();

/** Exercise read-only routes, historical readers and retained permissions. */
use Drupal\Core\Database\Database;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\Entity\Node;
use Drupal\xinshi_api\Controller\PanelsIPEPageController;
use Drupal\xinshi_api\PageDraftException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

umask(0077);
$started = microtime(TRUE);
$target = Database::getConnection();
$validation = json_decode(file_get_contents(Runtime::path('full-snapshot-validation-report.json')), TRUE, 512, JSON_THROW_ON_ERROR);
if ($target->getConnectionOptions()['database'] !== Runtime::targetDatabase() || $validation['status'] !== 'passed') {
  throw new RuntimeException('The verified prepared target is required.');
}
$target->query('SET SESSION wait_timeout = 7200');
$manager = \Drupal::entityTypeManager();
$nodes = $manager->getStorage('node');
$users = $manager->getStorage('user');
$switcher = \Drupal::service('account_switcher');
$anonymous = new AnonymousUserSession();
$admin = $users->load(1);
$errors = [];
$report = ['status' => 'checking', 'requests' => [], 'role_checks' => [], 'draft_history_checks' => [], 'alias_routes' => ['checked' => 0, 'failed' => 0], 'page_revisions' => ['checked' => 0, 'failed' => 0], 'read_only_requests' => TRUE, 'external_requests_sent' => FALSE, 'execution_environment' => Runtime::environment()];
$recordError = static function (string $kind, $key, Throwable $error) use (&$errors): void {
  $errors[] = ['kind' => $kind, 'key' => $key, 'class' => get_class($error), 'message' => $error->getMessage(), 'trace' => $error->getTraceAsString()];
};
$check = static function (string $name, bool $passed) use (&$report, &$errors): void {
  $report['role_checks'][$name] = $passed;
  if (!$passed) {
    $errors[] = ['kind' => 'permission', 'key' => $name];
  }
};
$requestOrigin = \Drupal::request()->getSchemeAndHttpHost();
$request = static function (string $name, string $path, $account, array $expected, ?callable $validate = NULL) use ($requestOrigin, $switcher, &$report, &$errors, $recordError): void {
  $switcher->switchTo($account);
  try {
    $request = Request::create($requestOrigin . $path, 'GET');
    $request->headers->set('Accept', str_starts_with($path, '/jsonapi/') ? 'application/vnd.api+json' : 'application/json');
    $response = \Drupal::service('http_kernel')->handle($request, HttpKernelInterface::SUB_REQUEST, TRUE);
    $status = $response->getStatusCode();
    $passed = in_array($status, $expected, TRUE);
    if ($passed && $validate) {
      $passed = $validate(json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR));
    }
    $report['requests'][$name] = ['status_code' => $status, 'passed' => $passed];
    if (!$passed) {
      $errors[] = ['kind' => 'request', 'key' => $name, 'path' => $path, 'status_code' => $status, 'body' => $response->getContent()];
    }
  }
  catch (Throwable $error) {
    $report['requests'][$name] = ['passed' => FALSE];
    $recordError('request', $name, $error);
  }
  finally {
    $switcher->switchBack();
  }
};
$plans = json_decode(file_get_contents(Runtime::path('model-config-plan.private.json')), TRUE, 512, JSON_THROW_ON_ERROR);
$permissionNames = array_keys(\Drupal::service('user.permissions')->getPermissions());
foreach ($plans[''] as $name => $definition) {
  if (!str_starts_with($name, 'user.role.')) {
    continue;
  }
  $role = $manager->getStorage('user_role')->load($definition['id']);
  $expected = array_values(array_intersect($definition['permissions'] ?? [], $permissionNames));
  $actual = $role->getPermissions();
  sort($expected);
  sort($actual);
  $check($name . '.retained_permissions', $expected === $actual);
  $check($name . '.administrator_flag', (bool) ($definition['is_admin'] ?? FALSE) === $role->isAdmin());
}
unset($plans);
$ordinaryIds = $target->query('SELECT u.uid FROM users_field_data u WHERE u.uid > 1 AND u.status = 1 AND u.default_langcode = 1 AND NOT EXISTS (SELECT 1 FROM user__roles r WHERE r.entity_id = u.uid) ORDER BY u.uid LIMIT 2')->fetchCol();
if (count($ordinaryIds) !== 2) {
  throw new RuntimeException('Two ordinary accounts are needed for access checks.');
}
$owner = $users->load($ordinaryIds[0]);
$other = $users->load($ordinaryIds[1]);
$draft = Node::create(['type' => 'landing_page', 'uid' => $owner->id(), 'status' => 0, 'title' => 'Migration access check']);
$check('anonymous_cannot_view_unpublished_page', !$draft->access('view', $anonymous));
$check('anonymous_cannot_update_page', !$draft->access('update', $anonymous));
$check('other_user_cannot_view_owned_draft', !$draft->access('view', $other));
$check('other_user_cannot_update_owned_draft', !$draft->access('update', $other));
$check('owner_edit_permission_preserved', $draft->access('update', $owner) === $owner->hasPermission('edit own landing_page content'));
foreach (['landing_page', 'conversation', 'ai_session', 'json', 'blog'] as $bundle) {
  $request('jsonapi_' . $bundle . '_admin', '/jsonapi/node/' . $bundle . '?page%5Blimit%5D=1', $admin, [200], static fn($json) => isset($json['data']) && is_array($json['data']));
}
foreach (['image', 'video', 'document'] as $bundle) {
  if ($manager->getStorage('media_type')->load($bundle)) {
    $request('jsonapi_media_' . $bundle . '_admin', '/jsonapi/media/' . $bundle . '?page%5Blimit%5D=1', $admin, [200], static fn($json) => isset($json['data']) && is_array($json['data']));
  }
}
$pageId = $target->query("SELECT nid FROM node_field_data WHERE type = 'landing_page' AND status = 1 AND default_langcode = 1 ORDER BY nid LIMIT 1")->fetchField();
$page = $nodes->load($pageId);
$request('public_page_canonical', '/api/v3/landingPage/json/' . $pageId, $anonymous, [200], static fn($json) => !empty($json['status']) && isset($json['body']));
$request('page_revision_list_admin', '/api/v3/landingPage/revisions/' . $pageId, $admin, [200], static fn($json) => !empty($json['status']) && isset($json['revisions']));
$request('page_revision_list_anonymous_denied', '/api/v3/landingPage/revisions/' . $pageId, $anonymous, [401, 403]);
$request('page_revision_read_admin', '/api/v3/landingPage/revisions/' . $pageId . '/' . $page->getRevisionId(), $admin, [200], static fn($json) => !empty($json['status']) && isset($json['revision_vid']));
$request('page_revision_read_anonymous_denied', '/api/v3/landingPage/revisions/' . $pageId . '/' . $page->getRevisionId(), $anonymous, [401, 403]);
$request('draft_capabilities_anonymous_denied', '/api/v3/landingPage/drafts/capabilities', $anonymous, [401, 403]);
$request('draft_capabilities_owner', '/api/v3/landingPage/drafts/capabilities', $owner, [200], static fn($json) => isset($json['permissions']) && is_array($json['permissions']));
$request('renamed_component_rest_resource_admin', '/api/v3/node/component?_format=json', $admin, [200]);

// Read-only historical operation lookup: absent objects must remain inaccessible.
foreach ($target->query('SELECT execution_id, uid, nid FROM xinshi_page_draft_operation') as $operation) {
  $actor = $users->load($operation->uid);
  $node = $nodes->load($operation->nid);
  $switcher->switchTo($actor);
  $passed = FALSE;
  try {
    $result = \Drupal::service('xinshi_api.page_drafts')->findDraft($operation->execution_id);
    $passed = $node && isset($result['result']);
  }
  catch (PageDraftException $error) {
    $passed = !$node && $error->reason === 'result_inaccessible' && $error->httpStatus === 403;
  }
  catch (Throwable $error) {
    $recordError('draft_history', $operation->execution_id, $error);
  }
  finally {
    $switcher->switchBack();
  }
  $foreign = (string) $other->id() === (string) $actor->id() ? $owner : $other;
  $switcher->switchTo($foreign);
  try {
    \Drupal::service('xinshi_api.page_drafts')->findDraft($operation->execution_id);
    $passed = FALSE;
  }
  catch (PageDraftException $error) {
    $passed = $passed && $error->reason === 'not_found' && $error->httpStatus === 404;
  }
  finally {
    $switcher->switchBack();
  }
  $report['draft_history_checks'][] = ['node_exists' => (bool) $node, 'passed' => $passed];
  if (!$passed) {
    $errors[] = ['kind' => 'draft_history', 'execution_id' => $operation->execution_id];
  }
}

$switcher->switchTo($admin);
try {
  foreach ($target->query('SELECT id, path, alias, langcode FROM path_alias') as $alias) {
    $report['alias_routes']['checked']++;
    try {
      $path = \Drupal::service('path_alias.manager')->getPathByAlias($alias->path, $alias->langcode);
      \Drupal::service('router.no_access_checks')->match($path);
    }
    catch (Throwable $error) {
      $report['alias_routes']['failed']++;
      $recordError('alias_route', $alias->id, $error);
    }
  }
  $revisions = $target->query("SELECT r.nid, r.vid FROM node_revision r INNER JOIN node n ON n.nid = r.nid WHERE n.type = 'landing_page' ORDER BY r.nid, r.vid");
  foreach ($revisions as $revision) {
    $report['page_revisions']['checked']++;
    try {
      $controller = PanelsIPEPageController::create(\Drupal::getContainer());
      $json = json_decode($controller->landingPageRevisionCanonical($nodes->load($revision->nid), $revision->vid)->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
      if (empty($json['status']) || (string) $json['revision_vid'] !== (string) $revision->vid || !is_array($json['body'])) {
        throw new RuntimeException('Historical page JSON did not load.');
      }
    }
    catch (Throwable $error) {
      $report['page_revisions']['failed']++;
      $recordError('page_revision', $revision->vid, $error);
    }
    if ($report['page_revisions']['checked'] % 100 === 0) {
      print json_encode(['page_revisions_checked' => $report['page_revisions']['checked'], 'page_revision_errors' => $report['page_revisions']['failed']], JSON_THROW_ON_ERROR) . PHP_EOL;
      $nodes->resetCache();
      $manager->getStorage('block_content')->resetCache();
    }
  }
}
finally {
  $switcher->switchBack();
}
$report['error_count'] = count($errors);
$report['status'] = $errors ? 'failed' : 'passed';
$report['elapsed_seconds'] = round(microtime(TRUE) - $started, 2);
file_put_contents(Runtime::path('api-and-access-report.json'), json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
if ($errors) {
  file_put_contents(Runtime::path('api-and-access-errors.private.json'), json_encode($errors, JSON_THROW_ON_ERROR));
}
print json_encode(array_diff_key($report, ['requests' => TRUE, 'role_checks' => TRUE, 'draft_history_checks' => TRUE]), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
if ($errors) {
  exit(1);
}
