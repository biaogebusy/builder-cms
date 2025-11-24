<?php

namespace Drupal\Tests\menu_breadcrumb\Unit\Breadcrumbs;

use Drupal\Tests\UnitTestCase;
use Drupal\menu_breadcrumb\MenuBasedBreadcrumbBuilder;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversDefaultClass \Drupal\menu_breadcrumb\MenuBasedBreadcrumbBuilder
 * @group menu_breadcrumb
 */
class MenuBasedBreadcrumbBuilderTest extends UnitTestCase {

  /**
   * The path based breadcrumb builder object to test.
   *
   * @var \Drupal\system\PathBasedBreadcrumbBuilder
   */
  protected $builder;

  /**
   * The stubbed configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockBuilder
   */
  protected $configFactory;

  /**
   * The mocked menu active trail.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $menuActiveTrail;

  /**
   * The mocked menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $menuLinkManager;

  /**
   * The mocked router admin context.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $routerAdminContext;

  /**
   * The mocked title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $titleResolver;

  /**
   * The mocked request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $requestStack;

  /**
   * The mocked language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cache;

  /**
   * The mocked lock.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $lock;

  /**
   * The default configuration.
   *
   * @var array
   */
  protected $installConfiguration = [];

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  protected function setUp(): void {
    parent::setUp();

    // Path to module default install configuration.
    $modulePath = dirname(__FILE__) . '/../../..';

    // Default configuration state at install.
    $this->installConfiguration = Yaml::parseFile("{$modulePath}/config/install/menu_breadcrumb.settings.yml");

    $this->configFactory = $this->getConfigFactoryStub(['menu_breadcrumb.settings' => $this->installConfiguration]);
    $this->menuActiveTrail = $this->createMock('\Drupal\Core\Menu\MenuActiveTrailInterface');
    $this->menuLinkManager = $this->createMock('Drupal\Core\Menu\MenuLinkManagerInterface');
    $this->routerAdminContext = $this->createMock('\Drupal\Core\Routing\AdminContext');
    $this->titleResolver = $this->createMock('\Drupal\Core\Controller\TitleResolverInterface');
    $this->requestStack = $this->createMock('\Symfony\Component\HttpFoundation\RequestStack');
    $this->languageManager = $this->createMock('\Drupal\Core\Language\LanguageManagerInterface');
    $this->entityTypeManager = $this->createMock('\Drupal\Core\Entity\EntityTypeManagerInterface');
    $this->cache = $this->createMock('\Drupal\Core\Cache\DatabaseBackend');
    $this->lock = $this->createMock('\Drupal\Core\Lock\LockBackendInterface');

    $this->builder = new MenuBasedBreadcrumbBuilder(
      $this->configFactory,
      $this->menuActiveTrail,
      $this->menuLinkManager,
      $this->routerAdminContext,
      $this->titleResolver,
      $this->requestStack,
      $this->languageManager,
      $this->entityTypeManager,
      $this->cache,
      $this->lock
    );
  }

  /**
   * Test that we can initialize our builder.
   *
   * @covers ::__construct
   */
  public function testInitialize() {
    $this->assertInstanceOf(MenuBasedBreadcrumbBuilder::class, $this->builder);
  }

}
