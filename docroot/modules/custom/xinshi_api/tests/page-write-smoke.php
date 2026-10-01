<?php

declare(strict_types=1);

/**
 * @file
 * Checks page writes on the disposable site made by integration-smoke.php.
 *
 * Run only in the isolated /app container, never against an existing site.
 */

use Drupal\block_content\Entity\BlockContentType;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\DrupalKernel;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_api\Controller\PanelsIPEPageController;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_API_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    $root !== '/app' || !is_file('/tmp/xinshi-api-test/site.sqlite')) {
  throw new RuntimeException('Use the disposable integration-smoke.php site only.');
}
require __DIR__ . '/bootstrap.php';
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
$request = Request::create('http://api-test/');
$kernel = DrupalKernel::createFromRequest($request, $loader, 'prod', FALSE);
$kernel->boot();
$kernel->preHandle($request);

function check(bool $condition, string $description): void {
  if (!$condition) {
    throw new RuntimeException($description);
  }
  echo 'PASS: ' . $description . PHP_EOL;
}

if (!NodeType::load('landing_page')) {
  NodeType::create(['type' => 'landing_page', 'name' => 'Landing page'])->save();
}
if (!BlockContentType::load('json')) {
  BlockContentType::create(['id' => 'json', 'label' => 'JSON'])->save();
}
if (!FieldStorageConfig::loadByName('block_content', 'body')) {
  FieldStorageConfig::create([
    'entity_type' => 'block_content', 'field_name' => 'body', 'type' => 'text_long',
  ])->save();
}
if (!FieldConfig::loadByName('block_content', 'json', 'body')) {
  FieldConfig::create([
    'entity_type' => 'block_content', 'bundle' => 'json', 'field_name' => 'body',
    'label' => 'Body',
  ])->save();
}
if (!FilterFormat::load('json')) {
  FilterFormat::create(['format' => 'json', 'name' => 'JSON', 'filters' => []])->save();
}
if (!LayoutBuilderEntityViewDisplay::load('node.landing_page.default')) {
  LayoutBuilderEntityViewDisplay::create([
    'targetEntityType' => 'node', 'bundle' => 'landing_page', 'mode' => 'default', 'status' => TRUE,
  ])->enableLayoutBuilder()->setOverridable()->save();
}
$role = Role::load('page_author') ?? Role::create(['id' => 'page_author', 'label' => 'Page author']);
$permissions = ['access content', 'create landing_page content', 'edit own landing_page content',
  'create json block content', 'edit any json block content', 'use text format json'];
$role->set('permissions', $permissions)->save();
$account = User::create(['name' => uniqid('page-author-'), 'status' => 1, 'roles' => ['page_author']]);
$account->save();
\Drupal::currentUser()->setAccount($account);
check((int) $account->id() !== 1 && !$account->hasPermission('administer nodes'), 'Author has no administrator bypass');

$probe = Node::create(['type' => 'landing_page', 'title' => 'Probe', 'uid' => $account->id()]);
check($probe->hasField('layout_builder__layout'), 'Layout field is installed');
check(!$probe->get('layout_builder__layout')->access('edit', $account), 'Core intentionally denies direct layout field editing');
check($probe->get('title')->access('edit', $account), 'Author can edit the page title');
check(\Drupal::entityTypeManager()->getAccessControlHandler('node')->createAccess('landing_page', $account), 'Author can create landing pages');
check(\Drupal::entityTypeManager()->getAccessControlHandler('block_content')->createAccess('json', $account), 'Author can create JSON components');
check(FilterFormat::load('json')->access('use', $account), 'Author can use the JSON text format');

$input = ['title' => 'About us', 'body' => [
  ['type' => 'json', 'attributes' => ['body' => ['type' => 'banner-simple', 'title' => '<p>About us</p>']]],
  ['type' => 'json', 'attributes' => ['body' => ['type' => 'text', 'body' => '<p>Introduction</p>']]],
]];
$controller = PanelsIPEPageController::create(\Drupal::getContainer());
$stack = \Drupal::service('request_stack');
$stack->push(Request::create('/api/v3/landingPage/builder', 'POST', content: json_encode($input)));
try {
  $response = $controller->landingPageBuilder();
}
finally {
  $stack->pop();
}
check($response->getStatusCode() === 200, 'Authorized author creates a page: ' . $response->getContent());
$data = json_decode($response->getContent(), TRUE);
$page = Node::load($data['data']['nid']);
check($page->isPublished() && (string) $page->getOwnerId() === (string) $account->id(), 'Creation preserves publication and ownership');
check(count($page->get('layout_builder__layout')->getSections()) === 2, 'Both submitted components are stored as layout sections');

// This fixture owns the isolated site; counts check that refusals do not write.
function entityCounts(): array {
  $counts = [];
  foreach (['node', 'block_content'] as $type) {
    $counts[$type] = \Drupal::entityQuery($type)->accessCheck(FALSE)->count()->execute();
  }
  return $counts;
}

$writer = \Drupal::service('xinshi_api.page_writer');
$sections = $page->get('layout_builder__layout')->getSections();
$components = $sections[0]->getComponents();
$component = reset($components);
$block = \Drupal::entityTypeManager()->getStorage('block_content')->loadRevision($component->get('configuration')['block_revision_id']);
$block_id = $block->id();
$revision_id = $block->getRevisionId();
$update = ['title' => 'Updated page', 'vid' => $page->getRevisionId(), 'body' => [[
  'uuid' => $block->uuid(), 'attributes' => ['body' => ['type' => 'text', 'body' => 'Updated content']],
]]];
$saved = $writer->updatePage($page, $update);
check($saved->id() === $page->id() && $saved->label() === 'Updated page', 'Author updates the same page');
check($saved->getRevisionId() !== $page->getRevisionId(), 'Page update creates a new page revision');
$shared = $writer->createPage(['title' => 'Shared page', 'body' => $update['body']]);
check($shared->id() !== $page->id(), 'Shared UUID is still accepted when creating another page');
$stored = BlockContent::load($block_id);
check((string) $stored->getRevisionId() === (string) $revision_id, 'Shared component keeps its existing revision identity');
check(str_contains($stored->get('body')->value, 'Updated content'), 'Shared component content is updated in place');

foreach (['create landing_page content', 'create json block content', 'use text format json'] as $index => $permission) {
  $role_id = 'page_refused_' . $index;
  $restricted = Role::load($role_id) ?? Role::create(['id' => $role_id, 'label' => $role_id]);
  $restricted->set('permissions', array_values(array_diff($permissions, [$permission])))->save();
  $denied = User::create(['name' => uniqid('denied-author-'), 'status' => 1, 'roles' => [$role_id]]);
  $denied->save();
  \Drupal::currentUser()->setAccount($denied);
  $before = entityCounts();
  try {
    $writer->createPage($input);
    throw new RuntimeException('Missing permission should refuse creation: ' . $permission);
  }
  catch (\Drupal\xinshi_api\PageDraftException $error) {
    check($error->reason === 'permission_denied' && $error->httpStatus === 403, 'Refuses missing ' . $permission);
  }
  check(entityCounts() === $before, 'No partial entities after refusing ' . $permission);
}
$other = User::create(['name' => uniqid('other-author-'), 'status' => 1, 'roles' => ['page_author']]);
$other->save();
\Drupal::currentUser()->setAccount($other);
$before = entityCounts();
try {
  $writer->updatePage($saved, ['title' => 'Forbidden edit', 'vid' => $saved->getRevisionId(), 'body' => $update['body']]);
  throw new RuntimeException('A non-owner without edit-any permission must be refused.');
}
catch (\Drupal\xinshi_api\PageDraftException $error) {
  check($error->reason === 'permission_denied', 'A non-owner cannot edit the original page');
}
check(entityCounts() === $before, 'Refused non-owner update creates no entities');
\Drupal::entityTypeManager()->getStorage('node')->resetCache([$saved->id()]);
check(Node::load($saved->id())->label() === 'Updated page', 'Refused non-owner update preserves the title');
echo "Isolated page write checks passed.\n";
