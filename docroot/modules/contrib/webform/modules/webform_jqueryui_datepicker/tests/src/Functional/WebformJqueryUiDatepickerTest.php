<?php

namespace Drupal\Tests\webform_jqueryui_datepicker\Functional;

use Drupal\Tests\webform\Functional\Element\WebformElementBrowserTestBase;

/**
 * Tests for webform elements with datepickers.
 *
 * @group webform_jqueryui_datepicker
 */
class WebformJqueryUiDatepickerTest extends WebformElementBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['webform_jqueryui_datepicker', 'webform_jqueryui_datepicker_test'];

  /**
   * Tests datepicker elements.
   */
  public function testDatepickerElements(): void {
    $assert_session = $this->assertSession();

    $this->drupalGet('/webform/test_element_datepicker');

    /* ********************************************************************** */
    // Render date/datetime elements.
    /* ********************************************************************** */

    // Check dynamic date picker.
    $min = date('D, m/d/Y', strtotime('-1 year'));
    $min_year = date('Y', strtotime('-1 year'));
    $max = date('D, m/d/Y', strtotime('+1 year'));
    $max_year = date('Y', strtotime('+1 year'));
    $default_value = date('D, m/d/Y', strtotime('now'));
    $assert_session->responseContains('<input min="' . $min . '" data-min-year="' . $min_year . '" max="' . $max . '" data-max-year="' . $max_year . '" type="text" data-drupal-date-format="D, m/d/Y" data-drupal-selector="edit-date-datepicker-min-max-dynamic" aria-describedby="edit-date-datepicker-min-max-dynamic--description" id="edit-date-datepicker-min-max-dynamic" name="date_datepicker_min_max_dynamic" value="' . $default_value . '" class="form-text" />');

    // Check date placeholder attribute.
    $assert_session->responseContains('<input placeholder="{date}" type="text" data-drupal-date-format="Y-m-d" data-drupal-selector="edit-date-datepicker-placeholder" id="edit-date-datepicker-placeholder" name="date_datepicker_placeholder" value="" class="form-text" />');

    // Check datetime picker.
    $timepickerDate = $assert_session->fieldExists('datetime_datepicker[date]');
    $this->assertEquals('Mon, 01/01/1900', $timepickerDate->getAttribute('min'));
    $this->assertEquals('Sat, 12/31/2050', $timepickerDate->getAttribute('max'));
    $this->assertEquals('Tue, 08/18/2009', $timepickerDate->getValue());

    /* ********************************************************************** */
    // Validate date/datetime elements.
    /* ********************************************************************** */

    // Check date #date_days validation.
    $this->drupalGet('/webform/test_element_datepicker');
    $edit = ['date_datepicker_weekend' => '2010-08-18'];
    $this->submitForm($edit, 'Submit');
    $assert_session->responseContains('<em class="placeholder">date_datepicker_weekend</em> must be a <em class="placeholder">Sunday or Saturday</em>.');

    /* ********************************************************************** */
    // Format date/datetime elements.
    /* ********************************************************************** */

    $this->drupalGet('/webform/test_element_datepicker');
    $this->submitForm([], 'Preview');

    // Check date formats.
    $this->assertElementPreview('date_datepicker', 'Tue, 08/18/2009');
    $this->assertElementPreview('date_datepicker_custom', 'Tuesday, August 18, 2009');
    $this->assertElementPreview('date_datepicker_min_max_dynamic', date('D, m/d/Y', strtotime('now')));
  }

}
