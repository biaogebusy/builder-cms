<?php

namespace Drupal\Tests\webform\Functional\Element;

/**
 * Tests for webform autocomplete element.
 *
 * @group webform
 */
class WebformElementAutocompleteTest extends WebformElementBrowserTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_element_autocomplete', 'test_element_starts_with'];

  /**
   * Tests CONTAINS autocomplete element.
   */
  public function testAutocompleteContains(): void {
    global $base_path;

    $assert_session = $this->assertSession();

    $this->drupalLogin($this->rootUser);

    /* Test #autocomplete property */

    $this->drupalGet('/webform/test_element_autocomplete');
    $assert_session->responseContains('<input autocomplete="off" data-drupal-selector="edit-autocomplete-off" type="email" id="edit-autocomplete-off" name="autocomplete_off" value="" size="60" maxlength="254" class="form-email" />');

    /* Test #autocomplete_items element property */

    // Check routes data-drupal-selector.
    $this->drupalGet('/webform/test_element_autocomplete');
    $assert_session->responseContains('<input data-drupal-selector="edit-autocomplete-items" class="form-autocomplete form-text webform-autocomplete" data-autocomplete-path="' . $base_path . 'webform/test_element_autocomplete/autocomplete/autocomplete_items" type="text" id="edit-autocomplete-items" name="autocomplete_items" value="" size="60" maxlength="255" />');

    // Check #autocomplete_items partial match.
    $this->drupalGet('/webform/test_element_autocomplete/autocomplete/autocomplete_items', ['query' => ['q' => 'United']]);
    $assert_session->responseContains('[{"value":"United Arab Emirates","label":"United Arab Emirates"},{"value":"United Kingdom","label":"United Kingdom"},{"value":"United States","label":"United States"}]');

    // Check #autocomplete_items exact match.
    $this->drupalGet('/webform/test_element_autocomplete/autocomplete/autocomplete_items', ['query' => ['q' => 'United States']]);
    $assert_session->responseContains('[{"value":"United States","label":"United States"}]');

    // Check #autocomplete_items just one character.
    $this->drupalGet('/webform/test_element_autocomplete/autocomplete/autocomplete_items', ['query' => ['q' => 'U']]);
    $assert_session->responseContains('[{"value":"Anguilla","label":"Anguilla"},{"value":"Antigua \u0026 Barbuda","label":"Antigua \u0026 Barbuda"},{"value":"Aruba","label":"Aruba"},{"value":"Australia","label":"Australia"},{"value":"Austria","label":"Austria"}]');

    /* Test #autocomplete_existing element property */

    // Check autocomplete is not enabled until there is a submission.
    $this->drupalGet('/webform/test_element_autocomplete');
    $assert_session->responseNotContains('<input data-drupal-selector="edit-autocomplete-existing" class="form-autocomplete form-text" data-autocomplete-path="' . $base_path . 'webform/test_element_autocomplete/autocomplete/autocomplete_existing" type="text" id="edit-autocomplete-existing" name="autocomplete_existing" value="" size="60" maxlength="255" />');
    $assert_session->responseContains('<input data-drupal-selector="edit-autocomplete-existing" type="text" id="edit-autocomplete-existing" name="autocomplete_existing" value="" size="60" maxlength="255" class="form-text webform-autocomplete" />');

    // Check #autocomplete_existing no match.
    $this->drupalGet('/webform/test_element_autocomplete/autocomplete/autocomplete_existing', ['query' => ['q' => 'abc']]);
    $assert_session->responseContains('[]');

    // Add #autocomplete_existing values to the submission table.
    $this->drupalGet('/webform/test_element_autocomplete');
    $edit = ['autocomplete_existing' => 'abcdefg'];
    $this->submitForm($edit, 'Submit');

    // Check #autocomplete_existing enabled now that there is submission.
    $this->drupalGet('/webform/test_element_autocomplete');
    $assert_session->responseContains('<input data-drupal-selector="edit-autocomplete-existing" class="form-autocomplete form-text webform-autocomplete" data-autocomplete-path="' . $base_path . 'webform/test_element_autocomplete/autocomplete/autocomplete_existing" type="text" id="edit-autocomplete-existing" name="autocomplete_existing" value="" size="60" maxlength="255" />');
    $assert_session->responseNotContains('<input data-drupal-selector="edit-autocomplete-existing" type="text" id="edit-autocomplete-existing" name="autocomplete_existing" value="" size="60" maxlength="255" class="form-text webform-autocomplete" />');

    // Check #autocomplete_existing match.
    $this->drupalGet('/webform/test_element_autocomplete/autocomplete/autocomplete_existing', ['query' => ['q' => 'abc']]);
    $assert_session->responseNotContains('[]');
    $assert_session->responseContains('[{"value":"abcdefg","label":"abcdefg"}]');

    // Check #autocomplete_existing minimum number of characters < 3.
    $this->drupalGet('/webform/test_element_autocomplete/autocomplete/autocomplete_existing', ['query' => ['q' => 'ab']]);
    $assert_session->responseContains('[]');
    $assert_session->responseNotContains('[{"value":"abcdefg","label":"abcdefg"}]');

    /* Test #autocomplete_existing and #autocomplete_items element property */

    // Add #autocomplete_body values to the submission table.
    $this->drupalGet('/webform/test_element_autocomplete');
    $edit = ['autocomplete_both' => 'Existing Item'];
    $this->submitForm($edit, 'Submit');

    // Check #autocomplete_both match.
    $this->drupalGet('/webform/test_element_autocomplete/autocomplete/autocomplete_both', ['query' => ['q' => 'Item']]);
    $assert_session->responseNotContains('[]');
    $assert_session->responseContains('[{"value":"Example Item","label":"Example Item"},{"value":"Existing Item","label":"Existing Item"}]');
  }

  /**
   * Tests STARTS_WITH autocomplete element.
   */
  public function testAutocompleteStartsWith(): void {
    $assert_session = $this->assertSession();

    // Log in as an admin to access the webform page.
    $this->drupalLogin($this->rootUser);

    /* Test #autocomplete_items using STARTS_WITH */

    // Navigate to the webform page to check the autocomplete field.
    $this->drupalGet('/webform/test_element_starts_with');

    // Check for partial match with "South" - only countries starting with "South" should appear.
    $this->drupalGet('/webform/test_element_starts_with/autocomplete/autocomplete_items', ['query' => ['q' => 'South']]);
    $assert_session->responseContains('[{"value":"South Africa","label":"South Africa"},{"value":"South Georgia \u0026 South Sandwich Islands","label":"South Georgia \u0026 South Sandwich Islands"},{"value":"South Korea","label":"South Korea"},{"value":"South Sudan","label":"South Sudan"}]');
    $assert_session->responseNotContains('North Korea');

    // Check for partial match with "Sudan" - only "Sudan" should appear.
    $this->drupalGet('/webform/test_element_starts_with/autocomplete/autocomplete_items', ['query' => ['q' => 'Sudan']]);
    $assert_session->responseContains('[{"value":"Sudan","label":"Sudan"}]');
    $assert_session->responseNotContains('South Sudan');

    // Check for exact match with "South Sudan" - only "South Sudan" should appear.
    $this->drupalGet('/webform/test_element_starts_with/autocomplete/autocomplete_items', ['query' => ['q' => 'South Sudan']]);
    $assert_session->responseContains('[{"value":"South Sudan","label":"South Sudan"}]');
    $assert_session->responseNotContains('[{"value":"Sudan","label":"Sudan"}]');

    // Check with input that doesn’t match any items, e.g., "xyz" - should return an empty array.
    $this->drupalGet('/webform/test_element_starts_with/autocomplete/autocomplete_items', ['query' => ['q' => 'xyz']]);
    $assert_session->responseContains('[]');
  }

}
