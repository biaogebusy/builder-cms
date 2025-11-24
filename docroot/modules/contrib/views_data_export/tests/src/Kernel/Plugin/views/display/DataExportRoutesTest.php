<?php

namespace Drupal\Tests\views_data_export\Kernel\Plugin\views\display;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests the data export view plugin routes.
 *
 * @group views_data_export
 */
class DataExportRoutesTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_admin_path'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views_data_export',
    'entity_test',
    'serialization',
    'rest',
    'views_data_export_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['views_test_config']):void {
    parent::setUp($import_test_views);

    ViewTestData::createTestViews(get_class($this), ['views_data_export_test']);
  }

  /**
   * Tests if routes are using batch export are marked as admin routes.
   */
  public function testBatchExportAdminPath() {
    $route_provider = \Drupal::service('router.route_provider');

    $admin_route = $route_provider->getRouteByName('view.test_admin_path.data_export_1');
    $this->assertTrue($admin_route->getOption('_admin_route'));

    $non_admin_route = $route_provider->getRouteByName('view.test_admin_path.data_export_2');
    $this->assertNotTrue($non_admin_route->getOption('_admin_route'));
  }

}
