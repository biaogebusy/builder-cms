<?php

namespace Drupal\Tests\address\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests the Views handlers provided by the Address module.
 *
 * @requires module views_ui
 * @group address
 */
class AddressViewsHandlersTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'address_test',
    'node',
    'views',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Prepare the test data.
    Node::create([
      'type' => 'address_test',
      'title' => 'United States',
      'status' => TRUE,
      'field_address_test' => [
        'country_code' => 'US',
        'administrative_area' => 'CA',
        'locality' => 'San Francisco',
        'postal_code' => '94103',
        'address_line1' => '1355 Market St',
      ],
    ])->save();
    Node::create([
      'type' => 'address_test',
      'title' => 'United States',
      'status' => TRUE,
      'field_address_test' => [
        'country_code' => 'US',
        'administrative_area' => 'NY',
        'locality' => 'New York',
        'postal_code' => '10001',
        'address_line1' => '350 5th Ave',
      ],
    ])->save();
    Node::create([
      'type' => 'address_test',
      'title' => 'Germany',
      'status' => TRUE,
      'field_address_test' => [
        'country_code' => 'DE',
        'locality' => 'Berlin',
        'postal_code' => '10115',
        'address_line1' => 'Alexanderplatz 1',
      ],
    ])->save();
    Node::create([
      'type' => 'address_test',
      'title' => 'France',
      'status' => TRUE,
      'field_address_test' => [
        'country_code' => 'FR',
        'locality' => 'Paris',
        'postal_code' => '75001',
        'address_line1' => '1 Rue de Rivoli',
      ],
    ])->save();
    Node::create([
      'type' => 'address_test',
      'title' => 'United Kingdom',
      'status' => TRUE,
      'field_address_test' => [
        'country_code' => 'GB',
        'locality' => 'London',
        'postal_code' => 'SW1A 1AA',
        'address_line1' => 'Buckingham Palace',
      ],
    ])->save();
    Node::create([
      'type' => 'address_test',
      'title' => 'Spain',
      'status' => TRUE,
      'field_address_test' => [
        'country_code' => 'ES',
        'administrative_area' => 'Madrid',
        'locality' => 'Madrid',
        'postal_code' => '28013',
        'address_line1' => 'Plaza de la Armería',
      ],
    ])->save();

    // Disable live preview.
    \Drupal::configFactory()->getEditable('views.settings')
      ->set('ui.always_live_preview', FALSE)->save();

    // Login with access to views ui.
    $this->drupalLogin($this->drupalCreateUser(['administer views']));
  }

  /**
   * Tests the address field handlers.
   *
   * @dataProvider addressFieldHandlersDataProvider
   */
  public function testAddressFieldHandlers($field_selector, $field_label, array $expected_name_value, array $expected_code_value) {
    $this->drupalGet('admin/structure/views/view/address_test_views_ui');

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Add field.
    $this->clickLink('views-add-field');

    $assert->waitForField($field_selector);
    $page->checkField($field_selector);
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Add and configure fields');

    // Make name visible in configs.
    $assert->waitForField('options[display_name]');
    $page->checkField('options[display_name]');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');

    // Check the field is added.
    $assert->waitForLink($field_label);

    // Refresh results and check if expected name values present.
    $page->find('css', '.preview-submit-wrapper')
      ->pressButton('Update preview');
    $assert->assertWaitOnAjaxRequest();

    $content = $page->find('css', '#views-live-preview')->getText();
    foreach ($expected_name_value as $value) {
      $this->assertStringContainsString($value, $content);
    }

    // Change setting to show code instead.
    $this->clickLink($field_label);
    $assert->waitForField('options[display_name]');
    $page->uncheckField('options[display_name]');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');

    // Check the field is not broken.
    $assert->waitForLink($field_label);

    // Refresh results and check if expected code values present.
    $page->find('css', '.preview-submit-wrapper')
      ->pressButton('Update preview');
    $assert->assertWaitOnAjaxRequest();

    $content = $page->find('css', '#views-live-preview')->getText();
    foreach ($expected_code_value as $value) {
      $this->assertStringContainsString($value, $content);
    }
  }

  /**
   * Data provider for testAddressFieldHandlers().
   */
  public function addressFieldHandlersDataProvider() {
    return [
      // Country field.
      [
        'name[node__field_address_test.field_address_test_country_code]',
        'Content: Address:country_code',
        ['Germany', 'France', 'United States', 'United Kingdom', 'Spain'],
        ['DE', 'FR', 'US', 'GB', 'ES'],
      ],
      // Administrative area field.
      [
        'name[node__field_address_test.field_address_test_administrative_area]',
        'Content: Address:administrative_area',
        ['California', 'New York', 'Madrid'],
        ['CA', 'NY', 'M'],
      ],
    ];
  }

  /**
   * Tests the address filter handlers.
   */
  public function testAddressFilterHandlers() {
    $this->drupalGet('admin/structure/views/view/address_test_views_ui');

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Add country and administrative area fields for testing.
    $fields = [
      'name[node__field_address_test.field_address_test_country_code]' => 'Content: Address:country_code',
      'name[node__field_address_test.field_address_test_administrative_area]' => 'Content: Address:administrative_area',
    ];
    foreach ($fields as $field_selector => $field_label) {
      $this->clickLink('views-add-field');

      $assert->waitForField($field_selector);
      $page->checkField($field_selector);
      $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
        ->pressButton('Add and configure fields');

      // Make name visible in configs.
      $assert->waitForField('options[display_name]');
      $page->checkField('options[display_name]');
      $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
        ->pressButton('Apply');

      // Check the field is added.
      $assert->assertWaitOnAjaxRequest();
      $assert->waitForLink($field_label);
    }

    // Add country filter.
    $this->clickLink('views-add-filter');

    $assert->waitForField('name[node__field_address_test.field_address_test_country_code]');
    $page->checkField('name[node__field_address_test.field_address_test_country_code]');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Add and configure filter criteria');

    // Select filter value.
    $assert->waitForField('options[value][US]');
    $page->checkField('options[value][US]');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');

    // Check the filter is added.
    $assert->waitForLink('Content: Address:country_code (= United States)');

    // Refresh results and check if only US results shown.
    $page->find('css', '.preview-submit-wrapper')
      ->pressButton('Update preview');
    $assert->assertWaitOnAjaxRequest();

    $content = $this->getViewsPreviewOutput();
    $this->assertStringContainsString('United States', $content);
    $this->assertStringContainsString('California', $content);
    $this->assertStringContainsString('New York', $content);
    $this->assertStringNotContainsString('Germany', $content);
    $this->assertStringNotContainsString('France', $content);
    $this->assertStringNotContainsString('United Kingdom', $content);
    $this->assertStringNotContainsString('Spain', $content);

    // Add administrative area filter.
    $this->clickLink('views-add-filter');

    $assert->waitForField('name[node__field_address_test.field_address_test_administrative_area]');
    $page->checkField('name[node__field_address_test.field_address_test_administrative_area]');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Add and configure filter criteria');

    // Select static country filter option.
    $assert->waitForField('options[country][country_source]');
    $page->find('css', 'input[name="options[country][country_source]"][value="static"]')->click();
    $assert->assertWaitOnAjaxRequest();
    $page->selectFieldOption('options[country][country_static_code]', 'US');
    $assert->assertWaitOnAjaxRequest();
    $page->checkField('options[value][CA]');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');

    // Check the filter is added.
    $assert->waitForLink('Content: Address:administrative_area (fixed country: US)');

    // Refresh results and check if only California result shown.
    $page->find('css', '.preview-submit-wrapper')
      ->pressButton('Update preview');
    $assert->assertWaitOnAjaxRequest();

    $content = $this->getViewsPreviewOutput();
    $this->assertStringContainsString('United States', $content);
    $this->assertStringContainsString('California', $content);
    $this->assertStringNotContainsString('New York', $content);
    $this->assertStringNotContainsString('Germany', $content);
    $this->assertStringNotContainsString('France', $content);
    $this->assertStringNotContainsString('United Kingdom', $content);
    $this->assertStringNotContainsString('Spain', $content);

    // Change country filter to use exposed input.
    $this->clickLink('Content: Address:country_code (= United States)');
    $assert->waitForField('options[expose_button][checkbox][checkbox]');
    $page->checkField('options[expose_button][checkbox][checkbox]');
    $assert->assertWaitOnAjaxRequest();
    $page->uncheckField('options[value][US]');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');
    $assert->waitForLink('Content: Address:country_code (exposed)');

    // Change administrative area filter to use exposed input country source.
    $this->clickLink('Content: Address:administrative_area (fixed country: US)');
    $assert->waitForField('options[expose_button][checkbox][checkbox]');
    $page->checkField('options[expose_button][checkbox][checkbox]');
    $assert->assertWaitOnAjaxRequest();
    $page->find('css', 'input[name="options[country][country_source]"][value="filter"]')->click();
    $assert->assertWaitOnAjaxRequest();
    $page->selectFieldOption('options[country][country_filter_id]', 'field_address_test_country_code');
    $page->find('css', 'input[name="options[expose][label_type]"][value="static"]')->click();
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');
    $assert->waitForLink('Content: Address:administrative_area (exposed: country set via exposed filter)');

    // Update preview and check exposed filters are present.
    $page->find('css', '.preview-submit-wrapper')
      ->pressButton('Update preview');
    $assert->assertWaitOnAjaxRequest();
    $assert->selectExists('field_address_test_country_code');
    $assert->optionExists('field_address_test_country_code', 'US');

    // Select US and check if states exist and do the filtering.
    $page->selectFieldOption('field_address_test_country_code', 'US');
    $page->find('css', '#views-live-preview')
      ->pressButton('Apply');
    $assert->assertWaitOnAjaxRequest();

    // Check if only US results are present.
    $views_output = $this->getViewsPreviewOutput();
    $this->assertStringContainsString('United States', $views_output);
    $this->assertStringContainsString('California', $views_output);
    $this->assertStringContainsString('New York', $views_output);
    $this->assertStringNotContainsString('Germany', $views_output);
    $this->assertStringNotContainsString('France', $views_output);
    $this->assertStringNotContainsString('United Kingdom', $views_output);
    $this->assertStringNotContainsString('Spain', $views_output);

    // Check if administrative area filter is present and has correct options.
    $assert->selectExists('field_address_test_administrative_area');
    $assert->optionExists('field_address_test_administrative_area', 'CA');
    $assert->optionExists('field_address_test_administrative_area', 'NY');

    // Select California and check results.
    $page->selectFieldOption('field_address_test_administrative_area', 'CA');
    $page->find('css', '#views-live-preview')
      ->pressButton('Apply');
    $assert->assertWaitOnAjaxRequest();

    // Check if only California result is present.
    $views_output = $this->getViewsPreviewOutput();
    $this->assertStringContainsString('United States', $views_output);
    $this->assertStringContainsString('California', $views_output);
    $this->assertStringNotContainsString('New York', $views_output);
    $this->assertStringNotContainsString('Germany', $views_output);
    $this->assertStringNotContainsString('France', $views_output);
    $this->assertStringNotContainsString('United Kingdom', $views_output);
    $this->assertStringNotContainsString('Spain', $views_output);

    // Remove country exposed filter and add contextual filter instead.
    $this->clickLink('Content: Address:country_code (exposed)');
    $assert->waitForElement('css', '.ui-dialog .ui-dialog-buttonpane');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Remove');
    $assert->assertWaitOnAjaxRequest();

    // Open advanced options.
    $page->find('css', 'summary:contains("Advanced")')->click();

    // Add country contextual filter.
    $this->clickLink('views-add-argument');
    $assert->waitForField('name[node__field_address_test.field_address_test_country_code]');
    $page->checkField('name[node__field_address_test.field_address_test_country_code]');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Add and configure contextual filters');
    $assert->assertWaitOnAjaxRequest();
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');
    $assert->assertWaitOnAjaxRequest();

    // Check if contextual filter is added.
    $page->find('css', 'summary:contains("Advanced")')->click();
    $assert->linkExists('Content: Address:country_code');

    // Set administrative area filter to use contextual country filter.
    $this->clickLink('Content: Address:administrative_area (exposed: country set via exposed filter)');
    $assert->waitForField('options[country][country_source]');
    $page->find('css', 'input[name="options[country][country_source]"][value="argument"]')->click();
    $assert->assertWaitOnAjaxRequest();
    $page->selectFieldOption('options[country][country_argument_id]', 'field_address_test_country_code');
    $page->find('css', 'input[name="options[expose][label_type]"][value="static"]')->click();
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');

    // Fill contextual argument, clear exposed and check results.
    $page->fillField('view_args', 'US');
    $page->selectFieldOption('field_address_test_administrative_area', 'All');
    $page->find('css', '.preview-submit-wrapper')
      ->pressButton('Update preview');
    $assert->assertWaitOnAjaxRequest();

    // Check if only US results are present.
    $views_output = $this->getViewsPreviewOutput();
    $this->assertStringContainsString('United States', $views_output);
    $this->assertStringContainsString('California', $views_output);
    $this->assertStringContainsString('New York', $views_output);
    $this->assertStringNotContainsString('Germany', $views_output);
    $this->assertStringNotContainsString('France', $views_output);
    $this->assertStringNotContainsString('United Kingdom', $views_output);
    $this->assertStringNotContainsString('Spain', $views_output);

    // Check administrative area filter is present and has correct options.
    $assert->selectExists('field_address_test_administrative_area');
    $assert->optionExists('field_address_test_administrative_area', 'CA');
    $assert->optionExists('field_address_test_administrative_area', 'NY');

    // Select California and check if results are filtered.
    $page->selectFieldOption('field_address_test_administrative_area', 'CA');
    $page->find('css', '#views-live-preview')
      ->pressButton('Apply');
    $assert->assertWaitOnAjaxRequest();

    // Check if only California result is present.
    $views_output = $this->getViewsPreviewOutput();
    $this->assertStringContainsString('United States', $views_output);
    $this->assertStringContainsString('California', $views_output);
    $this->assertStringNotContainsString('New York', $views_output);
    $this->assertStringNotContainsString('Germany', $views_output);
    $this->assertStringNotContainsString('France', $views_output);
    $this->assertStringNotContainsString('United Kingdom', $views_output);
    $this->assertStringNotContainsString('Spain', $views_output);
  }

  /**
   * Tests the address sort handlers.
   */
  public function testAddressSortHandlers() {
    $this->drupalGet('admin/structure/views/view/address_test_views_ui');

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Add country field for testing.
    $this->clickLink('views-add-field');
    $assert->waitForField('name[node__field_address_test.field_address_test_country_code]');
    $page->checkField('name[node__field_address_test.field_address_test_country_code]');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Add and configure fields');

    // Make name visible in configs.
    $assert->waitForField('options[display_name]');
    $page->checkField('options[display_name]');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');

    // Check the field is added.
    $assert->assertWaitOnAjaxRequest();
    $assert->waitForLink('Content: Address:country_code');

    // Set fields as inline and separated with dash.
    $this->clickLink('Change settings for this style');
    $assert->waitForField('row_options[inline][counter]');
    $page->checkField('row_options[inline][counter]');
    $page->checkField('row_options[inline][field_address_test_country_code]');
    $page->fillField('row_options[separator]', '-');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');
    $assert->assertWaitOnAjaxRequest();

    // Add and configure country sort criteria.
    $this->clickLink('views-add-sort');
    $assert->waitForField('name[node__field_address_test.field_address_test_country_code]');
    $page->checkField('name[node__field_address_test.field_address_test_country_code]');
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Add and configure sort criteria');
    $assert->waitForField('options[sort_by]');
    $page->find('css', 'input[name="options[sort_by]"][value="name"]')->click();
    $page->find('css', 'input[name="options[order]"][value="ASC"]')->click();
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');
    $assert->waitForLink('Content: Address:country_code (asc)');

    // Update results and check if sorted ascending by country name.
    $page->find('css', '.preview-submit-wrapper')
      ->pressButton('Update preview');
    $assert->assertWaitOnAjaxRequest();

    $content = $this->getViewsPreviewOutput();
    $this->assertStringContainsString('1-France', $content);
    $this->assertStringContainsString('2-Germany', $content);
    $this->assertStringContainsString('3-Spain', $content);
    $this->assertStringContainsString('4-United Kingdom', $content);
    $this->assertStringContainsString('5-United States', $content);
    $this->assertStringContainsString('6-United States', $content);

    // Change sort order to be descending.
    $this->clickLink('Content: Address:country_code (asc)');
    $assert->waitForField('options[order]');
    $page->find('css', 'input[name="options[sort_by]"][value="name"]')->click();
    $page->find('css', 'input[name="options[order]"][value="DESC"]')->click();
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');
    $assert->waitForLink('Content: Address:country_code (desc)');

    // Update results and check if sorted descending by country name.
    $page->find('css', '.preview-submit-wrapper')
      ->pressButton('Update preview');
    $assert->assertWaitOnAjaxRequest();

    $content = $this->getViewsPreviewOutput();
    $this->assertStringContainsString('1-United States', $content);
    $this->assertStringContainsString('2-United States', $content);
    $this->assertStringContainsString('3-United Kingdom', $content);
    $this->assertStringContainsString('4-Spain', $content);
    $this->assertStringContainsString('5-Germany', $content);
    $this->assertStringContainsString('6-France', $content);

    // Change sort order to be ascending by country code.
    $this->clickLink('Content: Address:country_code (desc)');
    $assert->waitForField('options[order]');
    $page->find('css', 'input[name="options[sort_by]"][value="code"]')->click();
    $page->find('css', 'input[name="options[order]"][value="ASC"]')->click();
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');
    $assert->waitForLink('Content: Address:country_code (asc)');

    // Update results and check if sorted ascending by country code.
    $page->find('css', '.preview-submit-wrapper')
      ->pressButton('Update preview');
    $assert->assertWaitOnAjaxRequest();

    $content = $this->getViewsPreviewOutput();
    $this->assertStringContainsString('1-Germany', $content);
    $this->assertStringContainsString('2-Spain', $content);
    $this->assertStringContainsString('3-France', $content);
    $this->assertStringContainsString('4-United Kingdom', $content);
    $this->assertStringContainsString('5-United States', $content);
    $this->assertStringContainsString('6-United States', $content);

    // Change sort order to be descending by country code.
    $this->clickLink('Content: Address:country_code (asc)');
    $assert->waitForField('options[order]');
    $page->find('css', 'input[name="options[sort_by]"][value="code"]')->click();
    $page->find('css', 'input[name="options[order]"][value="DESC"]')->click();
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')
      ->pressButton('Apply');
    $assert->waitForLink('Content: Address:country_code (desc)');

    // Update results and check if sorted descending by country code.
    $page->find('css', '.preview-submit-wrapper')
      ->pressButton('Update preview');
    $assert->assertWaitOnAjaxRequest();

    $content = $this->getViewsPreviewOutput();
    $this->assertStringContainsString('1-United States', $content);
    $this->assertStringContainsString('2-United States', $content);
    $this->assertStringContainsString('3-United Kingdom', $content);
    $this->assertStringContainsString('4-France', $content);
    $this->assertStringContainsString('5-Spain', $content);
    $this->assertStringContainsString('6-Germany', $content);
  }

  /**
   * Helper method to get views preview output without any other text.
   *
   * @return string
   *   The views preview output.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function getViewsPreviewOutput() {
    $this->assertSession()->elementExists('css', '#views-live-preview');
    $rows = $this->getSession()->getPage()
      ->findAll('css', '#views-live-preview .views-row');
    $content = '';
    foreach ($rows as $row) {
      $content .= $row->getText();
    }
    return $content;
  }

}
