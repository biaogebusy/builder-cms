<?php

declare(strict_types=1);

/**
 * @file
 * Exercises real Views and page caches on the disposable integration test site.
 *
 * Run after integration-smoke.php, only in a source-copy container at /app.
 * Each read and mutation runs in a new PHP process to avoid static cache reuse.
 */

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\entity_theme_engine\Entity\EntityWidget;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;
use Drupal\xinshi_api\Controller\LandingPageController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_API_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    $root !== '/app' || !is_file('/tmp/xinshi-api-test/site.sqlite')) {
  throw new RuntimeException('Use the disposable integration-smoke.php site only.');
}

function check(bool $condition, string $description): void {
  if (!$condition) {
    throw new RuntimeException($description);
  }
  echo 'PASS: ' . $description . PHP_EOL;
}

/** Runs one fresh request or mutation, returning only its structured result. */
function step(string $operation, array $options = []): array {
  $process = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', __FILE__, $operation, json_encode($options)],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
  $output = stream_get_contents($pipes[1]);
  $errors = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  if (proc_close($process) !== 0) {
    throw new RuntimeException($operation . ': ' . $errors . $output);
  }
  // Keep PHP diagnostics visible; only the final line is the JSON protocol.
  $lines = explode("\n", trim($output));
  $result = json_decode(array_pop($lines), TRUE, 512, JSON_THROW_ON_ERROR);
  if ($errors || $lines) {
    fwrite(STDERR, $errors . implode("\n", $lines));
  }
  return $result;
}

$operation = $argv[1] ?? 'run';
if ($operation === 'run') {
  $ids = step('setup');
  check($ids['ai_disabled'], 'Real site uses entity_theme_engine with AI modules disabled');
  $first = step('read', ['page' => 'host']);
  check($first['data']['rows'][0]['title'] === 'Row alpha', 'Real Views entity row renders through the page JSON controller');
  check($first['renders'] > 0 && $first['max_age'] !== 0, 'Fixture observes a cacheable page render');
  $hit = step('read', ['page' => 'host']);
  check($hit['data'] === $first['data'] && $hit['renders'] === $first['renders'], 'Second anonymous request reuses the persisted page cache');
  check($hit['max_age'] === -1, 'Database permanent expiration preserves cacheability on a hit');
  foreach (['node:' . $ids['row'], 'media:' . $ids['media'], 'file:' . $ids['file']] as $tag) {
    check(in_array($tag, $first['tags'], TRUE), 'Views response carries dependency ' . $tag);
  }
  check(in_array('timezone', $first['contexts'], TRUE), 'Late row-render cache context reaches the page response');

  foreach (['row' => ['title', 'Row alpha updated'], 'media' => ['media', 'Media updated'], 'file' => ['file', '/updated.txt']] as $target => [$key, $expected]) {
    step('mutate', ['target' => $target]);
    $fresh = step('read', ['page' => 'host']);
    check(str_contains($fresh['data']['rows'][0][$key], $expected), 'Updating ' . $target . ' invalidates the cached Views page');
    check($fresh['renders'] > $hit['renders'], 'Updating ' . $target . ' causes a page render');
    $hit = step('read', ['page' => 'host']);
    check($hit['renders'] === $fresh['renders'], 'Refreshed ' . $target . ' result is cacheable again');
  }
  $second = step('read', ['page' => 'host', 'query' => ['page' => 1]]);
  check($second['data']['rows'][0]['title'] === 'Row beta', 'Views page query selects the second row');
  $filtered = step('read', ['page' => 'host', 'query' => ['title' => 'beta']]);
  check($filtered['data']['rows'][0]['title'] === 'Row beta', 'Views exposed filter changes the output');
  $default = step('read', ['page' => 'host']);
  check($default['data']['rows'][0]['title'] === 'Row alpha updated', 'Pagination and filter variants do not overwrite the default page');
  step('mutate', ['target' => 'row_withdraw']);
  check(step('read', ['page' => 'host'])['data']['rows'][0]['title'] === 'Row beta', 'Withdrawing a Views row invalidates the list and excludes that row');
  step('mutate', ['target' => 'row_restore']);
  check(step('read', ['page' => 'host'])['data']['rows'][0]['title'] === 'Row alpha updated', 'Republishing a row restores the default list');

  $french = step('read', ['page' => 'host', 'language' => 'fr']);
  check($french['data']['title'] === 'Hote francais', 'Language-prefixed request resolves the French page translation');
  $english = step('read', ['page' => 'host']);
  check($english['data']['title'] === 'Views host', 'Alternating language requests do not reuse the other translation');
  $french_hit = step('read', ['page' => 'host', 'language' => 'fr']);
  check($french_hit['data'] === $french['data'] && $french_hit['renders'] === $english['renders'], 'French variant can be reused independently');
  step('mutate', ['target' => 'revision']);
  $current = step('read', ['page' => 'host']);
  $historical = step('read', ['page' => 'host', 'query' => ['revision' => $ids['host_revision']]]);
  check($current['data']['title'] === 'Views host revised' && $historical['data']['title'] === 'Views host', 'Current and historical revision requests keep separate output');
  check(step('read', ['page' => 'host'])['data'] === $current['data'], 'Historical cache warmup does not replace the current page');

  $a = step('read', ['page' => 'a']);
  $b = step('read', ['page' => 'b']);
  check($a['data']['body'][0]['title'] === 'Shared before' && $b['data']['body'][0]['title'] === 'Shared before', 'Two pages render the same stored JSON component');
  check(step('read', ['page' => 'b'])['renders'] === $b['renders'], 'Second page cache is warmed before the shared update');
  $shared = step('mutate', ['target' => 'shared']);
  check($shared['uuid'] === $ids['block_uuid'] && $shared['revision'] === $ids['block_revision'], 'Shared writer update keeps the existing component UUID and revision');
  foreach (['a', 'b'] as $page) {
    $fresh = step('read', ['page' => $page]);
    check($fresh['data']['body'][0]['title'] === 'Shared after', 'Writer update refreshes shared component on page ' . $page);
  }
  $http = step('http', ['page' => 'b']);
  $http_hit = step('http', ['page' => 'b']);
  check($http['status'] === 200 && $http['data']['body'][0]['title'] === 'Shared after', 'Anonymous request resolves through the real Drupal HTTP kernel and route');
  check(str_contains($http_hit['cache_control'], 'private') && $http_hit['renders'] === $http['renders'], 'Route prevents shared HTTP response caching while reusing page data');
  step('mutate', ['target' => 'block_access', 'value' => TRUE]);
  check(step('read', ['page' => 'b'])['data']['body'] === [], 'Layout Builder access denial still excludes the shared block after cache warmup');
  step('mutate', ['target' => 'block_access', 'value' => FALSE]);
  check(step('read', ['page' => 'b'])['data']['body'][0]['title'] === 'Shared after', 'Restoring block access invalidates the denied page variant');

  foreach (['noCache', 'nocache', 'preview'] as $flag) {
    $one = step('read', ['page' => 'host', 'query' => [$flag => 1]]);
    $two = step('read', ['page' => 'host', 'query' => [$flag => 1]]);
    check($two['renders'] > $one['renders'] && $two['max_age'] === 0, $flag . ' bypasses the persisted page cache');
  }
  foreach (['author', 'other'] as $account) {
    $one = step('read', ['page' => 'host', 'account' => $account]);
    $two = step('read', ['page' => 'host', 'account' => $account]);
    check($two['renders'] > $one['renders'] && $two['max_age'] === 0, 'Authenticated ' . $account . ' does not reuse page output');
  }
  step('mutate', ['target' => 'row_age', 'value' => 0]);
  $one = step('read', ['page' => 'host']);
  $two = step('read', ['page' => 'host']);
  check($two['renders'] > $one['renders'] && $two['max_age'] === 0, 'Uncacheable real row makes the enclosing page uncacheable');
  step('mutate', ['target' => 'row_age', 'value' => 60]);
  $finite = step('read', ['page' => 'host']);
  $finite_hit = step('read', ['page' => 'host']);
  check($finite['max_age'] > 0 && $finite['max_age'] <= 60 && $finite_hit['max_age'] <= $finite['max_age'] && $finite_hit['renders'] === $finite['renders'], 'Finite row lifetime is preserved on a page cache hit');
  step('mutate', ['target' => 'withdraw']);
  $withdrawn = step('read', ['page' => 'host']);
  check($withdrawn['data'] === ['denied' => TRUE] && $withdrawn['max_age'] === 0, 'Withdrawing a previously cached page prevents anonymous reuse');
  echo "Isolated page cache checks passed.\n";
  exit;
}

require __DIR__ . '/bootstrap.php';
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
$options = json_decode($argv[2] ?? '{}', TRUE, 512, JSON_THROW_ON_ERROR);
$ids = is_file('/tmp/xinshi-api-test/cache-fixture.json') ? json_decode(file_get_contents('/tmp/xinshi-api-test/cache-fixture.json'), TRUE) : [];
$query = ['content' => '/node/' . ($ids[$options['page'] ?? 'host'] ?? 1)] + ($options['query'] ?? []);
$prefix = isset($options['language']) ? '/' . $options['language'] : '';
$request = Request::create('http://api-test' . $prefix . '/api/v3/landingPage?' . http_build_query($query));
$kernel = DrupalKernel::createFromRequest($request, $loader, 'prod', FALSE);
$kernel->boot();
$kernel->preHandle($request);

/** Adds a field only to the disposable fixture's content model. */
function field(string $type, string $bundle, string $name, string $field_type, array $settings = []): void {
  if (!FieldStorageConfig::loadByName($type, $name)) {
    FieldStorageConfig::create(['entity_type' => $type, 'field_name' => $name, 'type' => $field_type, 'settings' => $settings])->save();
  }
  FieldConfig::create(['entity_type' => $type, 'bundle' => $bundle, 'field_name' => $name, 'label' => $name])->save();
}

if ($operation === 'setup') {
  if ($ids) {
    throw new RuntimeException('Use a fresh disposable fixture for every run.');
  }
  new \Drupal\Core\Site\Settings(['extension_discovery_scan_tests' => TRUE] + \Drupal\Core\Site\Settings::getAll());
  file_put_contents('sites/default/settings.php', "\n\$settings['extension_discovery_scan_tests'] = TRUE;\n", FILE_APPEND);
  \Drupal::service('extension.list.module')->reset();
  \Drupal::service('module_installer')->install(['media', 'content_translation', 'page_cache', 'dynamic_page_cache', 'xinshi_cache_test']);
  ConfigurableLanguage::createFromLangcode('fr')->save();
  \Drupal::configFactory()->getEditable('language.negotiation')->set('url.prefixes', ['en' => '', 'fr' => 'fr'])->save();
  \Drupal::configFactory()->getEditable('language.types')->set('negotiation.language_interface.enabled', ['language-url' => 0])->save();
  \Drupal::configFactory()->getEditable('xinshi_api.settings')->set('cache_enable', TRUE)->set('page.access_denied', '{"denied":true}')->save();
  Role::load('anonymous')->grantPermission('access content')->grantPermission('view media')->save();
  Role::create(['id' => 'cache_author', 'label' => 'Cache author', 'permissions' => ['access content', 'view media', 'create landing_page content', 'edit own landing_page content']])->save();
  foreach (['author', 'other'] as $name) {
    $user = User::create(['name' => 'cache-' . $name, 'status' => 1, 'roles' => ['cache_author']]);
    $user->save();
    $ids[$name] = $user->id();
  }
  foreach (['cache_host', 'cache_row'] as $type) {
    NodeType::create(['type' => $type, 'name' => $type])->save();
  }
  \Drupal::service('content_translation.manager')->setEnabled('node', 'cache_host', TRUE);
  MediaType::create(['id' => 'document', 'label' => 'Document', 'source' => 'file', 'source_configuration' => ['source_field' => 'field_media_file']])->save();
  field('media', 'document', 'field_media_file', 'file');
  field('node', 'cache_row', 'field_asset', 'entity_reference', ['target_type' => 'media']);
  file_put_contents('sites/default/files/original.txt', 'Cache fixture');
  $file = File::create(['uri' => 'public://original.txt', 'filename' => 'original.txt', 'status' => 1]);
  $file->save();
  $media = Media::create(['bundle' => 'document', 'name' => 'Media before', 'status' => 1, 'field_media_file' => ['target_id' => $file->id()]]);
  $media->save();
  $ids['file'] = $file->id();
  $ids['media'] = $media->id();
  foreach (['alpha', 'beta'] as $name) {
    $row = Node::create(['type' => 'cache_row', 'title' => 'Row ' . $name, 'status' => 1, 'field_asset' => ['target_id' => $media->id()]]);
    $row->save();
    $ids[$name === 'alpha' ? 'row' : 'row2'] = $row->id();
  }
  EntityViewMode::create(['id' => 'node.json', 'targetEntityType' => 'node', 'label' => 'JSON'])->save();
  EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'cache_row', 'mode' => 'teaser', 'status' => TRUE])->save();
  EntityWidget::create(['id' => 'cache_row', 'label' => 'Cache row JSON', 'entity_type' => 'node', 'bundle' => 'cache_row', 'display' => 'teaser',
    'template' => '{"title":{{ entity_label|json_encode|raw }},"media":{{ field_asset.entity_label|json_encode|raw }},"file":{{ field_asset.field_media_file.file_url|json_encode|raw }}}'])->save();
  $view = View::create(['id' => 'cache_rows', 'label' => 'Cache rows', 'base_table' => 'node_field_data', 'base_field' => 'nid',
    'display' => ['default' => ['id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0, 'display_options' => [
      'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
      'cache' => ['type' => 'tag'], 'query' => ['type' => 'views_query'],
      'style' => ['type' => 'default'], 'row' => ['type' => 'entity:node', 'options' => ['view_mode' => 'teaser']],
      'pager' => ['type' => 'full', 'options' => ['items_per_page' => 1]],
      'sorts' => ['nid' => ['id' => 'nid', 'table' => 'node_field_data', 'field' => 'nid', 'plugin_id' => 'standard', 'order' => 'ASC']],
      'filters' => [
        'type' => ['id' => 'type', 'table' => 'node_field_data', 'field' => 'type', 'plugin_id' => 'bundle', 'value' => ['cache_row' => 'cache_row']],
        'status' => ['id' => 'status', 'table' => 'node_field_data', 'field' => 'status', 'plugin_id' => 'boolean', 'value' => '1'],
        'title' => ['id' => 'title', 'table' => 'node_field_data', 'field' => 'title', 'plugin_id' => 'string', 'operator' => 'contains', 'exposed' => TRUE, 'expose' => ['identifier' => 'title', 'label' => 'Title']],
      ],
    ]], 'block_1' => ['id' => 'block_1', 'display_title' => 'Block', 'display_plugin' => 'block', 'position' => 1, 'display_options' => []]]]);
  $view->save();
  $section = new Section('layout_onecol');
  $section->appendComponent(new SectionComponent(\Drupal::service('uuid')->generate(), 'content', ['id' => 'views_block:cache_rows-block_1', 'provider' => 'views']));
  LayoutBuilderEntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'cache_host', 'mode' => 'json', 'status' => TRUE])->enableLayoutBuilder()->appendSection($section)->save();
  EntityWidget::create(['id' => 'cache_host', 'label' => 'Cache host JSON', 'entity_type' => 'node', 'bundle' => 'cache_host', 'display' => 'json',
    'template' => '{"title":{{ entity_label|json_encode|raw }},"rows":[{% for row in cache_rows_block_1.rows %}{{ row|render|striptags|raw }}{% if not loop.last %},{% endif %}{% endfor %}]}'])->save();
  $host = Node::create(['type' => 'cache_host', 'title' => 'Views host', 'status' => 1, 'langcode' => 'en']);
  $host->addTranslation('fr', ['title' => 'Hote francais', 'status' => 1]);
  $host->save();
  $ids['host'] = $host->id();
  $ids['host_revision'] = $host->getRevisionId();

  BlockContentType::create(['id' => 'json', 'label' => 'JSON'])->save();
  field('block_content', 'json', 'body', 'text_long');
  EntityViewMode::create(['id' => 'block_content.json', 'targetEntityType' => 'block_content', 'label' => 'JSON'])->save();
  EntityViewDisplay::create(['targetEntityType' => 'block_content', 'bundle' => 'json', 'mode' => 'json', 'status' => TRUE])->save();
  foreach (['banner_style', 'transparent_style', 'meta_tags'] as $name) {
    field('node', 'landing_page', $name, 'string_long');
  }
  field('node', 'landing_page', 'is_transparent', 'boolean');
  EntityWidget::create(['id' => 'cache_block', 'label' => 'JSON component', 'entity_type' => 'block_content', 'bundle' => 'json', 'display' => 'json', 'template' => '{{ body.value|raw }}'])->save();
  LayoutBuilderEntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'landing_page', 'mode' => 'default', 'status' => TRUE])->enableLayoutBuilder()->setOverridable()->save();
  \Drupal::currentUser()->setAccount(User::load($ids['author']));
  $writer = \Drupal::service('xinshi_api.page_writer');
  $body = [['type' => 'json', 'attributes' => ['body' => ['type' => 'text', 'title' => 'Shared before']]]];
  $a = $writer->createPage(['title' => 'Page A', 'body' => $body]);
  $components = $a->get('layout_builder__layout')->getSections()[0]->getComponents();
  $component = reset($components);
  $block = \Drupal::entityTypeManager()->getStorage('block_content')->loadRevision($component->get('configuration')['block_revision_id']);
  $ids['block'] = $block->id();
  $ids['block_revision'] = $block->getRevisionId();
  $ids['block_uuid'] = $block->uuid();
  $body[0]['uuid'] = $block->uuid();
  \Drupal::currentUser()->setAccount(User::load($ids['other']));
  $b = $writer->createPage(['title' => 'Page B', 'body' => $body]);
  $ids['a'] = $a->id();
  $ids['b'] = $b->id();
  $ids['ai_disabled'] = !\Drupal::moduleHandler()->moduleExists('xinshi_ai');
  file_put_contents('/tmp/xinshi-api-test/cache-fixture.json', json_encode($ids));
  echo json_encode($ids) . PHP_EOL;
}
elseif (in_array($operation, ['read', 'http'], TRUE)) {
  \Drupal::currentUser()->setAccount(isset($options['account']) ? User::load($ids[$options['account']]) : new AnonymousUserSession());
  // Direct controller probes still need the normal request language setup.
  \Drupal::service('language_request_subscriber')->onKernelRequestLanguage(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
  $response = $operation === 'http' ? $kernel->handle($request) : LandingPageController::create(\Drupal::getContainer())->landingPage($request);
  $metadata = $response->getCacheableMetadata();
  echo json_encode(['data' => json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR),
    'tags' => $metadata->getCacheTags(), 'contexts' => $metadata->getCacheContexts(), 'max_age' => $metadata->getCacheMaxAge(),
    'status' => $response->getStatusCode(), 'cache_control' => $response->headers->get('Cache-Control'),
    'renders' => \Drupal::state()->get('xinshi_cache_test.renders.' . $ids[$options['page']], 0)]) . PHP_EOL;
}
elseif ($operation === 'mutate') {
  $result = [];
  switch ($options['target']) {
    case 'row':
      Node::load($ids['row'])->setTitle('Row alpha updated')->save();
      break;

    case 'media':
      Media::load($ids['media'])->setName('Media updated')->save();
      break;

    case 'file':
      file_put_contents('sites/default/files/updated.txt', 'Updated cache fixture');
      $file = File::load($ids['file']);
      $file->setFileUri('public://updated.txt');
      $file->save();
      break;

    case 'shared':
      \Drupal::currentUser()->setAccount(User::load($ids['author']));
      $page = Node::load($ids['a']);
      \Drupal::service('xinshi_api.page_writer')->updatePage($page, ['title' => 'Page A updated', 'vid' => $page->getRevisionId(),
        'body' => [['uuid' => $ids['block_uuid'], 'type' => 'json', 'attributes' => ['body' => ['type' => 'text', 'title' => 'Shared after']]]]]);
      $block = \Drupal::entityTypeManager()->getStorage('block_content')->load($ids['block']);
      $result = ['uuid' => $block->uuid(), 'revision' => $block->getRevisionId()];
      break;

    case 'row_withdraw':
      Node::load($ids['row'])->setUnpublished()->save();
      break;

    case 'row_restore':
      Node::load($ids['row'])->setPublished()->save();
      break;

    case 'revision':
      $page = Node::load($ids['host']);
      $page->setNewRevision(TRUE);
      $page->setTitle('Views host revised')->save();
      break;

    case 'row_age':
      \Drupal::state()->set('xinshi_cache_test.row_age', $options['value']);
      // Changing a render hook's test setting requires invalidating its inputs.
      \Drupal::service('cache_tags.invalidator')->invalidateTags(['node:' . $ids['row']]);
      break;

    case 'block_access':
      \Drupal::state()->set('xinshi_cache_test.deny_block', $options['value'] ? $ids['block'] : NULL);
      \Drupal::service('cache_tags.invalidator')->invalidateTags(['cache_test:block_access']);
      break;

    case 'withdraw':
      Node::load($ids['host'])->setUnpublished()->save();
      break;
  }
  echo json_encode($result) . PHP_EOL;
}
else {
  throw new InvalidArgumentException('Unknown fixture operation.');
}
