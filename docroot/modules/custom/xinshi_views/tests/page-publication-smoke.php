<?php

declare(strict_types=1);

/**
 * @file
 * Checks rendered and raw language fields through a real Views REST display.
 *
 * Run after xinshi_api/tests/integration-smoke.php in its disposable container.
 */

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Render\RenderContext;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\views\display\RestExport;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_API_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
    $root !== '/app' || !is_file('/tmp/xinshi-api-test/site.sqlite')) {
  throw new RuntimeException('Use the disposable integration-smoke.php site only.');
}
require $root . '/docroot/modules/custom/xinshi_api/tests/bootstrap.php';
chdir($root . '/docroot');
$request = Request::create('http://api-test/api/v2/node/landing-page?noCache=1');
$request->setRequestFormat('json');
$kernel = DrupalKernel::createFromRequest($request, $loader, 'prod');
$kernel->boot();
$kernel->preHandle($request);
\Drupal::service('module_installer')->install(['xinshi_views', 'content_translation']);
\Drupal::currentUser()->setAccount(User::load(1));
if (!ConfigurableLanguage::load('fr')) {
  ConfigurableLanguage::createFromLangcode('fr')->save();
}
$page = Node::create(['type' => 'landing_page', 'title' => 'Unpublished English',
  'status' => 0, 'langcode' => 'en', 'uid' => 1]);
$page->addTranslation('fr', ['title' => 'Published French', 'status' => 1]);
$page->save();

function check(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  echo 'PASS: ' . $message . PHP_EOL;
}

foreach ([FALSE, TRUE] as $raw) {
  $id = ($raw ? 'publication_raw_' : 'publication_rendered_') . $page->id();
  $fields = [];
  foreach (['nid', 'title', 'langcode'] as $field) {
    $fields[$field] = ['id' => $field, 'table' => 'node_field_data',
      'field' => $field, 'plugin_id' => 'standard', 'label' => ''];
  }
  $view = View::create(['id' => $id, 'label' => $id, 'base_table' => 'node_field_data',
    'base_field' => 'nid', 'display' => [
      'default' => ['id' => 'default', 'display_plugin' => 'default', 'position' => 0,
        'display_options' => [
          'access' => ['type' => 'none'], 'cache' => ['type' => 'none'],
          'query' => ['type' => 'views_query'],
          'rendering_language' => '***LANGUAGE_entity_translation***',
          'style' => ['type' => 'xinshi_pager_serializer', 'options' => ['formats' => ['json']]],
          'row' => ['type' => 'data_field', 'options' => [
            'field_options' => ['langcode' => ['raw_output' => $raw]],
          ]],
          'pager' => ['type' => 'full', 'options' => ['items_per_page' => 10]],
          'fields' => $fields,
          'filters' => ['nid' => ['id' => 'nid', 'table' => 'node_field_data',
            'field' => 'nid', 'plugin_id' => 'numeric', 'value' => ['value' => $page->id()]]],
        ]],
      'rest_export_1' => ['id' => 'rest_export_1', 'display_plugin' => 'rest_export',
        'position' => 1, 'display_options' => ['path' => 'api/v2/node/' . $id,
          'style' => ['type' => 'xinshi_pager_serializer', 'options' => ['formats' => ['json']]],
          'row' => ['type' => 'data_field', 'options' => [
            'field_options' => ['langcode' => ['raw_output' => $raw]],
          ]]]],
    ]]);
  $view->save();

  // Inspect the actual row plugin, so a fixture cannot silently replace Markup with a string.
  $executable = Views::executableFactory()->get($view);
  $executable->executeDisplay('rest_export_1');
  $rendered = \Drupal::service('renderer')->executeInRenderContext(new RenderContext(),
    fn() => $executable->rowPlugin->render($executable->result[0]));
  check($raw ? is_string($rendered['langcode']) : $rendered['langcode'] instanceof MarkupInterface,
    $id . ' uses the expected Drupal field value type');

  $response = RestExport::buildResponse($id, 'rest_export_1');
  check($response->getStatusCode() === 200, $id . ' renders a REST response');
  $data = json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
  $rows = array_column($data['rows'], NULL, 'langcode');
  check(count($rows) === 2 && $data['pager']['total_items'] === 2, $id . ' preserves both rows and pagination');
  check($rows[$raw ? 'en' : 'English']['published'] === FALSE, $id . ' reports the unpublished original');
  check($rows[$raw ? 'fr' : 'French']['published'] === TRUE, $id . ' reports the published translation');
}
echo 'Isolated Views publication checks passed.' . PHP_EOL;
