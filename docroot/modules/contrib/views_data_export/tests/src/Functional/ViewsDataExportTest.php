<?php

namespace Drupal\Tests\views_data_export\Functional;

use Drupal\Tests\views\Functional\ViewTestBase;

/**
 * Tests views data export views.
 *
 * @group views_data_export
 */
class ViewsDataExportTest extends ViewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'rest',
    'views_data_export',
    'views_data_export_test',
    'csv_serialization',
    'xls_serialization',
  ];

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_data_export'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['views_test_config']): void {
    parent::setUp($import_test_views, ['views_data_export_test']);
    $account = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($account);
  }

  /**
   * Test proper response.
   */
  public function testHttpResponse() {
    // Load the linked page display.
    $this->drupalGet('test/data_export/page');
    $this->assertSession()->statusCodeEquals(200);

    // Click on the link to export.
    $this->clickLink('Download JSON');
    $this->assertSession()->statusCodeEquals(200);
  }

}
