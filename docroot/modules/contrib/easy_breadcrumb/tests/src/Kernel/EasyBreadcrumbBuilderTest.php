<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Kernel;

use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Url;
use Drupal\easy_breadcrumb\EasyBreadcrumbBuilder;
use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests the easy breadcrumb builder.
 *
 * @group easy_breadcrumb
 */
class EasyBreadcrumbBuilderTest extends KernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = ['easy_breadcrumb', 'system', 'easy_breadcrumb_test'];

  /**
   * Tests the front page with an invalid path.
   */
  public function testFrontpageWithInvalidPaths() {
    \Drupal::configFactory()->getEditable(EasyBreadcrumbConstants::MODULE_SETTINGS)
      ->set('include_invalid_paths', TRUE)
      ->set('include_title_segment', TRUE)
      ->save();
    \Drupal::configFactory()->getEditable('system.site')
      ->set('page.front', '/path')
      ->save();

    $request_context = new RequestContext();

    $breadcrumb_builder = new EasyBreadcrumbBuilder($request_context,
      \Drupal::service('access_manager'),
      \Drupal::service('router'),
      \Drupal::service('request_stack'),
      \Drupal::service('path_processor_manager'),
      \Drupal::service('config.factory'),
      \Drupal::service('easy_breadcrumb.title_resolver'),
      \Drupal::service('current_user'),
      \Drupal::service('path.current'),
      \Drupal::service('plugin.manager.menu.link'),
      \Drupal::service('language_manager'),
      \Drupal::service('entity_type.manager'),
      \Drupal::service('entity.repository'),
      \Drupal::service('logger.factory'),
      \Drupal::service('messenger'),
      \Drupal::service('module_handler'),
      \Drupal::service('path.matcher')
    );

    $route_match = new RouteMatch('test_front', new Route('/front'));
    $result = $breadcrumb_builder->build($route_match);
    $this->assertCount(0, $result->getLinks());
  }

  /**
   * Provides data for the get title string test.
   */
  public function providerTestGetTitleString() {
    return [
      ['easy_breadcrumb_test.title_string'],
      ['easy_breadcrumb_test.title_formattable_markup'],
      ['easy_breadcrumb_test.title_markup'],
      ['easy_breadcrumb_test.title_translatable_markup'],
      ['easy_breadcrumb_test.title_render_array'],
    ];
  }

  /**
   * Tests getting title string from the various ways route titles can be set.
   *
   * @param string $route_name
   *   The route to test.
   *
   * @dataProvider providerTestGetTitleString
   */
  public function testGetTitleString($route_name) {
    $url = Url::fromRoute($route_name);
    $request_context = new RequestContext();

    $breadcrumb_builder = new EasyBreadcrumbBuilder($request_context,
      \Drupal::service('access_manager'),
      \Drupal::service('router'),
      \Drupal::service('request_stack'),
      \Drupal::service('path_processor_manager'),
      \Drupal::service('config.factory'),
      \Drupal::service('easy_breadcrumb.title_resolver'),
      \Drupal::service('current_user'),
      \Drupal::service('path.current'),
      \Drupal::service('plugin.manager.menu.link'),
      \Drupal::service('language_manager'),
      \Drupal::service('entity_type.manager'),
      \Drupal::service('entity.repository'),
      \Drupal::service('logger.factory'),
      \Drupal::service('messenger'),
      \Drupal::service('module_handler'),
      \Drupal::service('path.matcher')
    );

    $request = Request::create('/' . $url->getInternalPath());
    $router = \Drupal::service('router.no_access_checks');
    $route_match = new RouteMatch($route_name, $router->match($url->getInternalPath())['_route_object']);
    $result = $breadcrumb_builder->getTitleString($request, $route_match, []);
    $this->assertIsString($result);
  }

}
