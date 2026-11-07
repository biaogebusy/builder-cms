<?php

/**
 * @file
 * Page read regressions included by the disposable auth-http-smoke.php runner.
 */

use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;

if (getenv('XINSHI_API_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    !isset($accounts, $sessions, $server) ||
    \Drupal::database()->getConnectionOptions()['database'] !== '/tmp/xinshi-api-test/site.sqlite') {
  throw new RuntimeException('Use the disposable auth-http-smoke.php runner only.');
}
if (!BlockContentType::load('json')) {
  BlockContentType::create(['id' => 'json', 'label' => 'JSON'])->save();
}
if (!FieldStorageConfig::loadByName('block_content', 'body')) {
  FieldStorageConfig::create(['entity_type' => 'block_content', 'field_name' => 'body', 'type' => 'text_long'])->save();
}
if (!FieldConfig::loadByName('block_content', 'json', 'body')) {
  FieldConfig::create(['entity_type' => 'block_content', 'bundle' => 'json', 'field_name' => 'body', 'label' => 'Body'])->save();
}
$display = LayoutBuilderEntityViewDisplay::load('node.landing_page.default') ??
  LayoutBuilderEntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'landing_page',
    'mode' => 'default', 'status' => TRUE]);
$display->enableLayoutBuilder()->setOverridable()->save();
\Drupal::currentUser()->setAccount(User::load($accounts['admin']['uid']));
$body = [['type' => 'text', 'body' => '<p>Original first</p>'],
  ['type' => 'text', 'body' => '最后一个组件', 'nested' => ['keep' => ['exact', 42]]]];
$page = \Drupal::service('xinshi_api.page_writer')->createPage(['title' => 'Readable page',
  'body' => array_map(fn($component) => ['type' => 'json', 'attributes' => ['body' => $component]], $body)]);
$publishedId = (string) $page->id();
$draft = $page->createDuplicate();
$draft->setOwnerId($accounts['ordinary']['uid']);
$draft->setUnpublished();
$draft->save();
$draftId = (string) $draft->id();
$sectionComponents = $page->get('layout_builder__layout')->getSections()[0]->getComponents();
$inlineConfiguration = reset($sectionComponents)->get('configuration');
$block = \Drupal::entityTypeManager()->getStorage('block_content')->loadRevision($inlineConfiguration['block_revision_id']);
$block->setNewRevision(TRUE);
$block->set('body', ['format' => 'json', 'value' => '{"type":"text","body":"Newer block must not replace pinned content"}']);
$block->save();
check((string) $block->getRevisionId() !== (string) $inlineConfiguration['block_revision_id'],
  'Fixture has a newer block revision than the page reference');
$before = [$page->toArray(), $draft->toArray()];
$path = '/api/v3/landingPage/page/';
$adminToken = issueToken((string) $accounts['admin']['uid']);
$ordinaryToken = issueToken((string) $accounts['ordinary']['uid']);

foreach ([['jar' => $admin['jar']], ['bearer' => $adminToken]] as $auth) {
  foreach ([$publishedId => 'published', $draftId => 'draft'] as $id => $expected) {
    $response = status(http('GET', $path . $id, ...$auth), 200, 'Administrator reads ' . $expected);
    $snapshot = json_decode($response['body'], TRUE, 512, JSON_THROW_ON_ERROR);
    check($snapshot['id'] === (string) $id && $snapshot['status'] === $expected,
      'Read preserves numeric node ID and actual publication status');
    check($snapshot['body'] === $body, 'Read returns all original component fields, including the tail');
    check((bool) preg_match('/^[a-f0-9]{64}$/D', $snapshot['version']), 'Read returns a source version');
  }
}
status(http('GET', $path . $publishedId, bearer: $ordinaryToken), 200, 'Ordinary viewer reads another author published page');
status(http('GET', $path . $draftId, bearer: $ordinaryToken), 403, 'Ownership alone does not grant unpublished view access');
status(http('GET', $path . $draftId), 403, 'Anonymous caller cannot read the page tool endpoint');
status(http('GET', $path . '999999', bearer: $adminToken), 404, 'Missing page has a distinct response');
status(http('GET', '/api/v3/landingPage/drafts/page/' . $draftId, bearer: $adminToken), 403,
  'Administrator generic view access does not permit another author draft write preparation');
status(http('GET', '/api/v3/landingPage/drafts/page/' . $publishedId, bearer: $adminToken), 409,
  'Published page cannot be prepared as an owned draft');
foreach (['append', 'delete'] as $action) {
  $input = ['action' => $action, 'pageId' => $draftId, 'expectedVersion' => $snapshot['version']];
  if ($action === 'append') $input['body'] = [['type' => 'text', 'body' => 'Forbidden']];
  $response = status(http('POST', '/api/v3/landingPage/drafts/' . \Drupal::service('uuid')->generate() . '/change',
    bearer: $adminToken, body: json_encode($input)), 403, 'Administrator cannot ' . $action . ' another author draft');
  check(json_decode($response['body'], TRUE)['outcome'] === 'not_written', 'Refusal confirms no write');
}
$capabilities = json_decode(status(http('GET', '/api/v3/landingPage/drafts/capabilities', bearer: $ordinaryToken),
  200, 'Viewer reads capabilities')['body'], TRUE);
check(in_array('pages.read', $capabilities['permissions'], TRUE), 'Viewer discovers generic page read capability');
Role::create(['id' => 'page_reviewer', 'label' => 'Page reviewer', 'permissions' => [
  'access content', 'view any unpublished content',
]])->save();
$reviewer = User::create(['name' => 'page-reviewer', 'pass' => 'isolated-page-review-password',
  'status' => 1, 'roles' => ['page_reviewer']]);
$reviewer->save();
$reviewJar = $fixture . '/page-reviewer.cookies';
status(http('POST', '/user/login?_format=json', $reviewJar, body: json_encode([
  'name' => $reviewer->getAccountName(), 'pass' => 'isolated-page-review-password',
])), 200, 'Non-administrator reviewer signs in');
check(!$reviewer->hasPermission('bypass node access'), 'Reviewer has no administrator bypass');
status(http('GET', $path . $draftId, $reviewJar), 200,
  'Actual unpublished-view permission allows a non-administrator to read another author draft');
status(http('GET', '/api/v3/landingPage/drafts/page/' . $draftId, $reviewJar), 403,
  'Reviewer cannot prepare another author draft for writing');

// Metatag derives block canonical URLs from the current request. Exercise fresh
// HTTP kernels so service-only tests cannot hide route-dependent versions.
foreach ([['jar' => $admin['jar'], 'csrf' => $admin['csrf']], ['bearer' => $adminToken]] as $auth) {
  $operations = '/api/v3/landingPage/drafts/';
  $created = json_decode(status(http('POST', $operations . \Drupal::service('uuid')->generate(),
    ...$auth, body: json_encode(['title' => 'Metatag draft', 'body' => $body])),
    200, 'Owner creates a draft with Metatag enabled')['body'], TRUE);
  $id = $created['result']['id'];
  $read = json_decode(status(http('GET', $path . $id, ...$auth), 200, 'Owner reads the page')['body'], TRUE);
  foreach ([$operations . 'page/' . $id, $path . $id . '?noCache=1'] as $url) {
    $other = json_decode(status(http('GET', $url, ...$auth), 200, 'Owner reads through another URL')['body'], TRUE);
    check($other['version'] === $read['version'], 'Stored page version is independent of the request URL');
  }
  $change = ['action' => 'append', 'pageId' => $id, 'expectedVersion' => $read['version'],
    'body' => [['type' => 'text', 'body' => 'Appended once']]];
  $operation = $operations . \Drupal::service('uuid')->generate();
  $written = status(http('POST', $operation . '/change', ...$auth, body: json_encode($change)),
    200, 'Generic read version permits an owned draft append');
  $receipt = status(http('GET', $operation, ...$auth), 200, 'Owner reconciles the append');
  check(json_decode($receipt['body']) == json_decode($written['body']), 'Reconciliation returns the original append receipt');
  $duplicate = status(http('POST', $operation . '/change', ...$auth, body: json_encode($change)),
    200, 'Owner repeats the same append operation');
  check(json_decode($duplicate['body']) == json_decode($written['body']),
    'Duplicate append returns the original receipt');
  $after = json_decode(status(http('GET', $path . $id, ...$auth), 200, 'Owner reads the appended draft')['body'], TRUE);
  check($after['body'] === [...$body, ...$change['body']] && $after['version'] !== $read['version'],
    'Append preserves original components and advances the version exactly once');
  $stale = status(http('POST', $operations . \Drupal::service('uuid')->generate() . '/change',
    ...$auth, body: json_encode($change)), 409, 'A new operation rejects the stale page version');
  $refusal = json_decode($stale['body'], TRUE);
  check($refusal['code'] === 'version_conflict' && $refusal['outcome'] === 'not_written',
    'Version conflict remains a definite no-write result');

  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $storage->resetCache([$id]);
  $owned = $storage->load($id);
  $revision = $owned->getRevisionId();
  $components = $owned->get('layout_builder__layout')->getSections()[0]->getComponents();
  $uuid = substr(reset($components)->get('configuration')['id'], 14);
  $blocks = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties(['uuid' => $uuid]);
  $edited = reset($blocks);
  check($edited->getFieldDefinition('metatag')->isComputed(), 'Fixture includes the computed Metatag field');
  $edited->set('body', ['format' => 'json', 'value' => '{"type":"text","body":"Concurrent block edit"}'])->save();
  $storage->resetCache([$id]);
  check($storage->load($id)->getRevisionId() === $revision, 'Legacy block edit preserves the node revision');
  $change['expectedVersion'] = $after['version'];
  status(http('POST', $operations . \Drupal::service('uuid')->generate() . '/change',
    ...$auth, body: json_encode($change)), 409, 'A block edit alone invalidates the page version');
}
administratorRole(FALSE);
try {
  status(http('GET', $path . $draftId, $admin['jar']), 403, 'Administrator role revocation denies the next Cookie read');
  status(http('GET', $path . $draftId, bearer: $adminToken), 401, 'Revocation invalidates the old OAuth token');
}
finally {
  administratorRole(TRUE);
}
\Drupal::entityTypeManager()->getStorage('node')->resetCache([$publishedId, $draftId]);
check([Node::load($publishedId)->toArray(), Node::load($draftId)->toArray()] == $before,
  'Reads and refused writes preserve both complete pages');
echo "Isolated page read HTTP checks passed.\n";
