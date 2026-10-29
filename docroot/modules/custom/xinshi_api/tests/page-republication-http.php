<?php

/**
 * @file
 * Exercises direct republication in the disposable content-form HTTP fixture.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;

if (getenv('XINSHI_CONTENT_FORM_TEST') !== '1' || !is_file('/.dockerenv') ||
    \Drupal::database()->getConnectionOptions()['database'] !== '/tmp/content-form-test/site.sqlite') {
  throw new RuntimeException('Use the disposable content form HTTP runner only.');
}
NodeType::create(['type' => 'landing_page', 'name' => 'Landing page', 'new_revision' => TRUE])->save();
EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => 'landing_page',
  'mode' => 'default', 'status' => TRUE])->setComponent('title', ['type' => 'string_textfield'])->save();
$workflow = Workflow::create(['id' => 'editorial', 'label' => 'Editorial', 'type' => 'content_moderation',
  'type_settings' => [
    'states' => [
      'draft' => ['label' => 'Draft', 'published' => FALSE, 'default_revision' => FALSE, 'weight' => 0],
      'published' => ['label' => 'Published', 'published' => TRUE, 'default_revision' => TRUE, 'weight' => 1],
      'unpublished' => ['label' => 'Unpublished', 'published' => FALSE, 'default_revision' => TRUE, 'weight' => 2],
    ],
    'transitions' => [
      'draft' => ['label' => 'Draft', 'from' => ['draft', 'published', 'unpublished'], 'to' => 'draft', 'weight' => 0],
      'publish' => ['label' => 'Publish', 'from' => ['draft', 'published'], 'to' => 'published', 'weight' => 1],
      'withdraw' => ['label' => 'Unpublish', 'from' => ['published'], 'to' => 'unpublished', 'weight' => 2],
    ],
    'entity_types' => ['node' => ['landing_page']], 'default_moderation_state' => 'draft',
  ]]);
$workflow->save();
\Drupal::moduleHandler()->loadInclude('xinshi_api', 'install');
xinshi_api_update_10005();
$workflow = Workflow::load('editorial');
check($workflow->getTypePlugin()->hasTransitionFromStateToState('unpublished', 'published'),
  'Upgrade adds the direct transition to the actual workflow');

$editPermissions = ['access content', 'edit any landing_page content', 'view any unpublished content',
  'use editorial transition draft'];
foreach (['page_editor' => $editPermissions, 'page_publisher' => array_merge($editPermissions,
  ['use editorial transition publish', 'use editorial transition withdraw'])] as $roleId => $permissions) {
  Role::create(['id' => $roleId, 'label' => $roleId, 'permissions' => $permissions])->save();
  Oauth2Scope::create(['name' => $roleId, 'granularity_id' => 'role',
    'granularity_configuration' => ['role' => $roleId],
    'grant_types' => ['authorization_code' => ['status' => TRUE]]])->save();
}
$publisher = User::create(['name' => 'page-publisher', 'status' => 1,
  'roles' => ['page_editor', 'page_publisher']]);
$publisher->save();
$publisherToken = token((string) $publisher->id(), 'page_publisher');
$editorToken = token((string) $publisher->id(), 'page_editor');
$page = Node::create(['type' => 'landing_page', 'title' => 'Withdrawn page',
  'uid' => $publisher->id(), 'moderation_state' => 'unpublished']);
$page->save();
\Drupal::service('router.builder')->rebuild();
$formPath = '/api/v3/node-form/landing_page/' . $page->uuid();
$pagePath = '/jsonapi/node/landing_page/' . $page->uuid();
$publishPatch = json_encode(['data' => ['type' => 'node--landing_page', 'id' => $page->uuid(),
  'attributes' => ['moderation_state' => 'published']]]);

$form = status(http('GET', $formPath, bearer: $publisherToken), 200, 'Publisher reads page status');
check(in_array('published', array_column($form['states'], 'value'), TRUE),
  'Unpublished page offers published without an intermediate draft');
$limited = status(http('GET', $formPath, bearer: $editorToken), 200, 'Limited scope can edit page settings');
check(!in_array('published', array_column($limited['states'], 'value'), TRUE),
  'Limited scope does not inherit the account publication permission');
$denied = http('PATCH', $pagePath, bearer: $editorToken, body: $publishPatch, type: 'application/vnd.api+json');
check(in_array($denied['status'], [403, 422], TRUE), 'CMS rejects a forged direct publish with limited scope');
$saved = status(http('PATCH', $pagePath, bearer: $publisherToken, body: $publishPatch,
  type: 'application/vnd.api+json'), 200, 'One PATCH directly republishes the page');
check($saved['data']['attributes']['status'] === TRUE &&
  $saved['data']['attributes']['moderation_state'] === 'published', 'Published response confirms both states');
$current = status(http('GET', $pagePath, bearer: $publisherToken), 200, 'Read default revision after republication');
check($current['data']['attributes']['status'] === TRUE, 'Published page is the readable default revision');
$withdraw = json_encode(['data' => ['type' => 'node--landing_page', 'id' => $page->uuid(),
  'attributes' => ['moderation_state' => 'unpublished']]]);
status(http('PATCH', $pagePath, bearer: $publisherToken, body: $withdraw,
  type: 'application/vnd.api+json'), 200, 'Existing withdrawal transition still works');
Role::load('page_publisher')->revokePermission('use editorial transition publish')->save();
$denied = http('PATCH', $pagePath, bearer: $publisherToken, body: $publishPatch, type: 'application/vnd.api+json');
check(in_array($denied['status'], [403, 422], TRUE), 'Revocation after form loading blocks publication');
$current = status(http('GET', $pagePath, bearer: $publisherToken), 200, 'Read page after rejected publication');
check($current['data']['attributes']['status'] === FALSE, 'Rejected publication leaves the page unpublished');
