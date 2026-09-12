<?php

namespace Drupal\Tests\xls_serialization\Functional;

use Drupal\Tests\views\Functional\ViewTestBase;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Tests the Excel Export views style metadata form display.
 *
 * This test specifically checks that metadata default values are correctly
 * displayed in the Excel export views style configuration form.
 *
 * @group xls_serialization
 * @see https://www.drupal.org/project/xls_serialization/issues/3561100
 */
class ExcelExportMetadataTest extends ViewTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'xls_serialization',
    'views_ui',
    'node',
    'field',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [];

  /**
   * A user with administrative privileges to configure views.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = []): void {
    parent::setUp($import_test_views, $modules);

    // Create a content type for testing.
    $this->drupalCreateContentType(['type' => 'page']);

    // Create an admin user with permissions to administer views.
    $this->adminUser = $this->drupalCreateUser([
      'administer views',
      'administer site configuration',
    ]);

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that metadata default values are correctly displayed in the form.
   *
   * This test verifies the fix for issue #3561100 where metadata default
   * values were not correctly assigned to individual field elements due to
   * incorrect array key path in the form builder.
   */
  public function testMetadataDefaultValuesDisplay() {
    // Create a new Excel export view.
    $view_id = 'test_excel_metadata';
    $this->drupalGet('admin/structure/views/add');
    $this->submitForm([
      'label' => 'Test Excel Metadata',
      'id' => $view_id,
      'show[wizard_key]' => 'node',
      'page[create]' => FALSE,
    ], 'Save and edit');

    // Add an Excel export display.
    $this->submitForm([], 'Add Excel export');

    // Configure the Excel export display style options.
    $style_options_url = "admin/structure/views/nojs/display/{$view_id}/excel_export_1/style_options";
    $this->drupalGet($style_options_url);

    // Set metadata values.
    $metadata_values = [
      'creator' => 'Test Author',
      'last_modified_by' => 'Test Modifier',
      'title' => 'Test Document Title',
      'description' => 'Test Document Description',
      'subject' => 'Test Subject',
      'keywords' => 'test, metadata, excel',
      'category' => 'Test Category',
      'manager' => 'Test Manager',
      'company' => 'Test Company',
    ];

    $form_data = [
      'style_options[formats]' => 'xlsx',
    ];

    foreach ($metadata_values as $key => $value) {
      $form_data["style_options[xls_settings][metadata][$key]"] = $value;
    }

    $this->submitForm($form_data, 'Apply');
    $this->submitForm([], 'Save');

    // Reopen the style options form to verify default values are displayed.
    $this->drupalGet($style_options_url);

    // Verify that each metadata field displays its saved value as default.
    foreach ($metadata_values as $key => $value) {
      $field_name = "style_options[xls_settings][metadata][$key]";
      $field = $this->assertSession()->fieldExists($field_name);
      $this->assertEquals(
        $value,
        $field->getValue(),
        "The metadata field '$key' should display its saved value '$value' as default."
      );
    }
  }

  /**
   * Tests that metadata is correctly embedded in the exported Excel file.
   */
  public function testMetadataInExportedFile(): void {
    // Create test content.
    $this->drupalCreateNode(['type' => 'page', 'title' => 'Test Node']);

    // Create a view with Excel export.
    $view_id = 'test_excel_export';
    $this->drupalGet('admin/structure/views/add');
    $this->submitForm([
      'label' => 'Test Excel Export',
      'id' => $view_id,
      'show[wizard_key]' => 'node',
      'page[create]' => FALSE,
    ], 'Save and edit');

    $this->submitForm([], 'Add Excel export');

    // Set path and metadata.
    $this->drupalGet("admin/structure/views/nojs/display/$view_id/excel_export_1/path");
    $this->submitForm(['path' => 'test-export.xlsx'], 'Apply');

    $this->drupalGet("admin/structure/views/nojs/display/$view_id/excel_export_1/style_options");
    $this->submitForm([
      'style_options[formats]' => 'xlsx',
      'style_options[xls_settings][metadata][creator]' => 'Test Author',
      'style_options[xls_settings][metadata][title]' => 'Test Title',
      'style_options[xls_settings][metadata][company]' => 'Test Company',
    ], 'Apply');

    $this->submitForm([], 'Save');

    // Export and verify metadata.
    $url = $this->buildUrl('test-export.xlsx');
    $client = $this->getHttpClient();
    $response = $client->get($url);

    $this->assertEquals(200, $response->getStatusCode());
    $content = (string) $response->getBody();

    $file = tempnam(sys_get_temp_dir(), 'xlsx') . '.xlsx';
    file_put_contents($file, $content);

    $reader = IOFactory::createReader('Xlsx');
    $spreadsheet = $reader->load($file);
    $properties = $spreadsheet->getProperties();

    $this->assertEquals('Test Author', $properties->getCreator());
    $this->assertEquals('Test Title', $properties->getTitle());
    $this->assertEquals('Test Company', $properties->getCompany());

    unlink($file);
  }

}
