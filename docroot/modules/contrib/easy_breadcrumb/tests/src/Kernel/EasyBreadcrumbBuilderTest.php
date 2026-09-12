<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Kernel;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Routing\NullRouteMatch;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\easy_breadcrumb\EasyBreadcrumbBuilder;
use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests the easy breadcrumb builder.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbBuilderTest extends KernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'system',
    'easy_breadcrumb_test',
    'menu_link_content',
    'user',
    'field',
    'link',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');
  }

  /**
   * Gets a new EasyBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Routing\RequestContext $request_context
   *   The request context.
   *
   * @return \Drupal\easy_breadcrumb\EasyBreadcrumbBuilder
   *   The EasyBreadcrumbBuilder class.
   */
  protected function getEasyBreadcrumbBuilder(RequestContext $request_context): EasyBreadcrumbBuilder {
    return new EasyBreadcrumbBuilder($request_context,
      $this->container->get('access_manager'),
      $this->container->get('router'),
      $this->container->get('request_stack'),
      $this->container->get('path_processor_manager'),
      $this->container->get('config.factory'),
      $this->container->get('easy_breadcrumb.title_resolver'),
      $this->container->get('current_user'),
      $this->container->get('path.current'),
      $this->container->get('plugin.manager.menu.link'),
      $this->container->get('language_manager'),
      $this->container->get('entity_type.manager'),
      $this->container->get('entity.repository'),
      $this->container->get('logger.factory'),
      $this->container->get('messenger'),
      $this->container->get('module_handler'),
      $this->container->get('path.matcher')
    );
  }

  /**
   * Runs common assertions for a custom path.
   */
  protected function checkCustomPath(): void {
    $request = Request::create('/test/easy-breadcrumb-custom-path');
    $request_context = new RequestContext();
    $request_context->fromRequest($request);

    $breadcrumb_builder = $this->getEasyBreadcrumbBuilder($request_context);

    $result = $breadcrumb_builder->build(new NullRouteMatch());
    $this->assertCount(1, $result->getLinks());
    $this->assertEquals('Part 1', $result->getLinks()[0]->getText());
    $this->assertEquals('base:part-1', $result->getLinks()[0]->getUrl()->toUriString());
  }

  /**
   * Gets a breadcrumb from a custom route.
   *
   * @return Drupal\Core\Breadcrumb\Breadcrumb
   *   The core Breadcrumb class.
   */
  public function getBreadcrumbByCustomRoute(): Breadcrumb {
    $route_name = 'easy_breadcrumb_test.custom_path';

    $url = Url::fromRoute($route_name);
    $request_context = new RequestContext();

    $breadcrumb_builder = $this->getEasyBreadcrumbBuilder($request_context);

    $router = $this->container->get('router.no_access_checks');
    $route_match = new RouteMatch($route_name, $router->match($url->getInternalPath())['_route_object']);
    $result = $breadcrumb_builder->build($route_match);
    return $result;
  }

  /**
   * Runs assertions on a custom path.
   *
   * @param Drupal\Core\Breadcrumb\Breadcrumb $result
   *   The core Breadcrumb class.
   */
  public function assertCustomPath(Breadcrumb $result): void {
    $this->assertCount(2, $result->getLinks());
    $this->assertEquals('Part 1', $result->getLinks()[0]->getText());
    $this->assertEquals('base:part-1', $result->getLinks()[0]->getUrl()->toUriString());
    $this->assertEquals('Easy Breadcrumb Custom Path Test', $result->getLinks()[1]->getText());
    $this->assertEquals('route:<none>', $result->getLinks()[1]->getUrl()->toUriString());
  }

  /**
   * Tests the front page with an invalid path.
   */
  public function testFrontpageWithInvalidPaths() {
    $this->config(EasyBreadcrumbConstants::MODULE_SETTINGS)
      ->set('include_invalid_paths', TRUE)
      ->set('include_title_segment', TRUE)
      ->save();

    $this->config('system.site')
      ->set('page.front', '/path')
      ->save();

    $request_context = new RequestContext();
    $breadcrumb_builder = $this->getEasyBreadcrumbBuilder($request_context);

    $route_match = new RouteMatch('test_front', new Route('/front'));
    $result = $breadcrumb_builder->build($route_match);
    $this->assertCount(0, $result->getLinks());
  }

  /**
   * Provides data for the get title string test.
   */
  public static function providerTestGetTitleString() {
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
   */
  #[DataProvider('providerTestGetTitleString')]
  public function testGetTitleString($route_name) {
    $url = Url::fromRoute($route_name);
    $request_context = new RequestContext();

    $breadcrumb_builder = $this->getEasyBreadcrumbBuilder($request_context);

    $request = Request::create('/' . $url->getInternalPath());
    $router = $this->container->get('router.no_access_checks');
    $route_match = new RouteMatch($route_name, $router->match($url->getInternalPath())['_route_object']);
    $result = $breadcrumb_builder->getTitleString($request, $route_match, []);
    $this->assertIsString($result);
  }

  /**
   * Tests a custom path override with an unrouted URL. (Issue #3480899)
   */
  public function testCustomPathWithUnroutedUrl() {
    $this->config(EasyBreadcrumbConstants::MODULE_SETTINGS)
      ->set(EasyBreadcrumbConstants::CUSTOM_PATHS, '/test/easy-breadcrumb-custom-path :: Part 1 | /part-1')
      ->save();

    $this->checkCustomPath();
  }

  /**
   * Tests a custom path override with a route match. (Issue #3480899)
   */
  public function testCustomPathWithRoutedUrl() {
    $this->config(EasyBreadcrumbConstants::MODULE_SETTINGS)
      ->set(EasyBreadcrumbConstants::CUSTOM_PATHS, '/test/easy-breadcrumb-custom-path :: Part 1 | /part-1')
      ->save();

    $result = $this->getBreadcrumbByCustomRoute();
    $this->assertCount(1, $result->getLinks());
    $this->assertEquals('Part 1', $result->getLinks()[0]->getText());
    $this->assertEquals('base:part-1', $result->getLinks()[0]->getUrl()->toUriString());
  }

  /**
   * Tests a custom path override with regex.
   */
  public function testCustomPathWithRegex() {
    $this->config(EasyBreadcrumbConstants::MODULE_SETTINGS)
      ->set(EasyBreadcrumbConstants::CUSTOM_PATHS, 'regex!/test/.+ :: Part 1 | /part-1')
      ->save();

    $this->checkCustomPath();
  }

  /**
   * Tests a custom path override with title replacement and an unrouted url.
   *
   * @see https://drupal.org/i/3271576
   */
  public function testCustomPathWithTitleAndUnroutedUrl() {
    $this->config(EasyBreadcrumbConstants::MODULE_SETTINGS)
      ->set(EasyBreadcrumbConstants::CUSTOM_PATHS, 'regex!/test/.+ :: Part 1 | /part-1 :: <title>')
      ->set(EasyBreadcrumbConstants::TITLE_FROM_PAGE_WHEN_AVAILABLE, TRUE)
      ->save();

    $request = Request::create('/test/easy-breadcrumb-custom-path');
    $request_context = new RequestContext();
    $request_context->fromRequest($request);

    $breadcrumb_builder = $this->getEasyBreadcrumbBuilder($request_context);

    $result = $breadcrumb_builder->build(new NullRouteMatch());
    $this->assertCustomPath($result);
  }

  /**
   * Tests a custom path override with title replacement and a route match.
   *
   * @see https://drupal.org/i/3271576
   */
  public function testCustomPathWithTitleAndRoutedUrl() {
    $this->config(EasyBreadcrumbConstants::MODULE_SETTINGS)
      ->set(EasyBreadcrumbConstants::CUSTOM_PATHS, 'regex!/test/.+ :: Part 1 | /part-1 :: <title>')
      ->set(EasyBreadcrumbConstants::TITLE_FROM_PAGE_WHEN_AVAILABLE, TRUE)
      ->save();

    $result = $this->getBreadcrumbByCustomRoute();
    $this->assertCustomPath($result);
  }

  /**
   * Tests that anchor (fragment) menu links do not win the breadcrumb title.
   *
   * When "use menu title as fallback" is enabled and a page has menu links
   * pointing to anchors on the same page (e.g. /page#section), those links
   * share the page's route. The breadcrumb must use the page's own menu link
   * title, not the title of a fragment link.
   *
   * @see https://www.drupal.org/project/easy_breadcrumb/issues/3510983
   */
  public function testMenuTitleIgnoresFragmentLinks() {
    $this->config(EasyBreadcrumbConstants::MODULE_SETTINGS)
      ->set(EasyBreadcrumbConstants::USE_MENU_TITLE_AS_FALLBACK, TRUE)
      ->set(EasyBreadcrumbConstants::TITLE_FROM_PAGE_WHEN_AVAILABLE, FALSE)
      ->set(EasyBreadcrumbConstants::ALTERNATIVE_TITLE_FIELD, '')
      ->set(EasyBreadcrumbConstants::INCLUDE_TITLE_SEGMENT, TRUE)
      ->set(EasyBreadcrumbConstants::TITLE_SEGMENT_AS_LINK, TRUE)
      ->save();

    $route_name = 'easy_breadcrumb_test.custom_path';

    // Fragment links (weighted first) plus the page's own link. Before the fix
    // a fragment link's title ("2024") was chosen for the breadcrumb.
    MenuLinkContent::create([
      'title' => '2024',
      'link' => ['uri' => 'route:' . $route_name, 'options' => ['fragment' => '2024']],
      'menu_name' => 'main',
      'weight' => 1,
    ])->save();
    MenuLinkContent::create([
      'title' => '2008',
      'link' => ['uri' => 'route:' . $route_name, 'options' => ['fragment' => '2008']],
      'menu_name' => 'main',
      'weight' => 2,
    ])->save();
    MenuLinkContent::create([
      'title' => 'Publications',
      'link' => ['uri' => 'route:' . $route_name],
      'menu_name' => 'main',
      'weight' => 10,
    ])->save();

    $url = Url::fromRoute($route_name);
    $request_context = new RequestContext();

    $breadcrumb_builder = $this->getEasyBreadcrumbBuilder($request_context);

    $router = $this->container->get('router.no_access_checks');
    $route_match = new RouteMatch($route_name, $router->match($url->getInternalPath())['_route_object']);

    $result = $breadcrumb_builder->build($route_match);
    $titles = array_map(function ($link) {
      return (string) $link->getText();
    }, $result->getLinks());

    $this->assertContains('Publications', $titles);
    $this->assertNotContains('2024', $titles);
    $this->assertNotContains('2008', $titles);
  }

}
