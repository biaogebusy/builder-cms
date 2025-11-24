<?php

namespace Drupal\Tests\views_data_export\Functional;

use Drupal\Tests\views\Functional\ViewTestBase;

/**
 * Tests views data export views validation.
 *
 * @group views_data_export
 */
class ViewsDataExportValidateTest extends ViewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'rest',
    'views_data_export',
    'views_data_export_test',
    'csv_serialization',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_data_export_validate'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['views_test_config']): void {
    parent::setUp($import_test_views, ['views_data_export_test']);
    $account = $this->drupalCreateUser(['administer views']);
    $this->drupalLogin($account);
  }

  /**
   * Test our validation code.
   *
   * @covers \Drupal\views_data_export\Plugin\views\display\DataExport::validate
   */
  public function testCloneDisplayValidate() {
    // We have a view that has a block display that uses an overridden style
    // and row plugin.
    $this->drupalGet('admin/structure/views/view/test_data_export_validate/edit/block_1');
    $this->assertSession()->statusCodeEquals(200);

    // Duplicate this as a data export display.
    $this->submitForm([], 'Duplicate as Data export');

    // Now we're going to set the path for the new display, so that we can
    // save it.
    $this->drupalGet('admin/structure/views/nojs/display/test_data_export_validate/data_export_1/path');
    $this->submitForm([
      'path' => 'test/data_export/validate/export',
    ], 'Apply');

    // Views will have copied the style and row plugin from the block display,
    // which are not valid for a data export display. Try saving the view.
    $this->drupalGet('admin/structure/views/view/test_data_export_validate/edit/data_export_1');
    $this->submitForm([], 'Save');

    // It should complain about the style plugin first.
    $this->assertSession()->pageTextContains('does not use a valid style plugin.');

    // Set the valid style plugin.
    $this->drupalGet('admin/structure/views/nojs/display/test_data_export_validate/data_export_1/style');
    $this->submitForm([
      'style[type]' => 'data_export',
    ], 'Apply');

    // Try saving the view again.
    $this->drupalGet('admin/structure/views/view/test_data_export_validate/edit/data_export_1');
    $this->submitForm([], 'Save');

    // Now it should complain about the row plugin.
    $this->assertSession()->pageTextContains('does not use a valid row plugin.');

    // Now set the valid row plugin.
    $this->drupalGet('admin/structure/views/nojs/display/test_data_export_validate/data_export_1/row');
    $this->submitForm([
      'row[type]' => 'data_entity',
    ], 'Apply');

    // Try saving the view again.
    $this->drupalGet('admin/structure/views/view/test_data_export_validate/edit/data_export_1');
    $this->submitForm([], 'Save');

    // Now both errors should be cleared.
    $this->assertSession()->pageTextNotContains('does not use a valid style plugin.');
    $this->assertSession()->pageTextNotContains('does not use a valid row plugin.');

    // Finally, the view should save properly.
    $this->assertSession()->pageTextContains('The view test_data_export_validate has been saved.');
  }

}
