<?php

namespace Drupal\Tests\webform\Functional\Element;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\webform\Entity\Webform;

/**
 * Tests for webform datelist element.
 *
 * @group webform
 */
class WebformElementDateListTest extends WebformElementBrowserTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_element_datelist'];

  /**
   * Test datelist element.
   */
  public function testDateListElement(): void {
    $assert_session = $this->assertSession();

    $webform = Webform::load('test_element_datelist');

    // Check posted submission values.
    $this->postSubmission($webform);
    $assert_session->responseContains("datelist_default: '2009-08-18T16:00:00+1000'
datelist_date_part_title_display: '2009-08-18T16:00:00+1000'
datelist_no_abbreviate: '2009-08-18T16:00:00+1000'
datelist_text_parts: '2009-08-18T16:00:00+1000'
datelist_datetime: '2009-08-18T16:00:00+1000'
datelist_date: '2009-08-18T00:00:00+1000'
datelist_min_max: '2009-08-18T00:00:00+1000'
datelist_min_max_time: '2009-01-01T09:00:00+1100'
datelist_date_year_range_reverse: ''
datelist_required_error: '2009-08-18T16:00:00+1000'
datelist_conditional: 0
datelist_conditional_required: ''
datelist_multiple:
  - '2009-08-18T16:00:00+1000'
datelist_custom_composite:
  - datelist: '2009-08-18T16:00:00+1000'");

    $this->drupalGet('/webform/test_element_datelist');

    // Check date part title display.
    $assert_session->responseContains('<label for="edit-datelist-default-year" class="visually-hidden">datelist_default: Year</label>');
    $assert_session->responseContains('<label for="edit-datelist-date-part-title-display-year">datelist_date_part_title_display: Year</label>');

    // Check datelist label has not for attributes.
    $assert_session->responseContains('<label>datelist_default</label>');

    // Check '#format' values.
    $assert_session->fieldValueEquals('datelist_default[month]', '8');

    // Check '#date_abbreviate': false.
    $this->assertEquals('1', $assert_session->optionExists('datelist_no_abbreviate[month]', 'January')->getValue());
    $this->assertEquals('2', $assert_session->optionExists('datelist_no_abbreviate[month]', 'February')->getValue());
    $this->assertEquals('3', $assert_session->optionExists('datelist_no_abbreviate[month]', 'March')->getValue());
    $this->assertEquals('4', $assert_session->optionExists('datelist_no_abbreviate[month]', 'April')->getValue());
    $this->assertEquals('5', $assert_session->optionExists('datelist_no_abbreviate[month]', 'May')->getValue());
    $this->assertEquals('6', $assert_session->optionExists('datelist_no_abbreviate[month]', 'June')->getValue());
    $this->assertEquals('7', $assert_session->optionExists('datelist_no_abbreviate[month]', 'July')->getValue());
    $augustOption = $assert_session->optionExists('datelist_no_abbreviate[month]', 'August');
    $this->assertEquals('8', $augustOption->getValue());
    $this->assertTrue($augustOption->isSelected());
    $this->assertEquals('9', $assert_session->optionExists('datelist_no_abbreviate[month]', 'September')->getValue());
    $this->assertEquals('10', $assert_session->optionExists('datelist_no_abbreviate[month]', 'October')->getValue());
    $this->assertEquals('11', $assert_session->optionExists('datelist_no_abbreviate[month]', 'November')->getValue());
    $this->assertEquals('12', $assert_session->optionExists('datelist_no_abbreviate[month]', 'December')->getValue());

    // Check date year range reverse.
    $this->drupalGet('/webform/test_element_datelist');
    $this->assertEquals('2010', $assert_session->optionExists('datelist_date_year_range_reverse[year]', '2010')->getValue());
    $this->assertEquals('2009', $assert_session->optionExists('datelist_date_year_range_reverse[year]', '2009')->getValue());
    $this->assertEquals('2008', $assert_session->optionExists('datelist_date_year_range_reverse[year]', '2008')->getValue());
    $this->assertEquals('2007', $assert_session->optionExists('datelist_date_year_range_reverse[year]', '2007')->getValue());
    $this->assertEquals('2006', $assert_session->optionExists('datelist_date_year_range_reverse[year]', '2006')->getValue());
    $this->assertEquals('2005', $assert_session->optionExists('datelist_date_year_range_reverse[year]', '2005')->getValue());

    // Check 'datelist' and 'datetime' #default_value.
    $form = $webform->getSubmissionForm();
    $this->assertInstanceOf(DrupalDateTime::class, $form['elements']['datelist_default']['#default_value']);

    // Check datelist #date_date_max validation.
    $this->drupalGet('/webform/test_element_datelist');
    $edit = [
      'datelist_min_max[year]' => '2010',
      'datelist_min_max[month]' => '8',
      'datelist_min_max[day]' => '18',
    ];
    $this->submitForm($edit, 'Submit');
    $assert_session->responseContains('<em class="placeholder">datelist_min_max</em> must be on or before <em class="placeholder">2009-12-31</em>.');

    // Check datelist #date_date_min validation.
    $edit = [
      'datelist_min_max[year]' => '2006',
      'datelist_min_max[month]' => '8',
      'datelist_min_max[day]' => '18',
    ];
    $this->drupalGet('/webform/test_element_datelist');
    $this->submitForm($edit, 'Submit');
    $assert_session->responseContains('<em class="placeholder">datelist_min_max</em> must be on or after <em class="placeholder">2009-01-01</em>.');

    // Check datelist #date_max validation.
    $this->drupalGet('/webform/test_element_datelist');
    $edit = [
      'datelist_min_max_time[year]' => '2009',
      'datelist_min_max_time[month]' => '12',
      'datelist_min_max_time[day]' => '31',
      'datelist_min_max_time[hour]' => '18',
    ];
    $this->submitForm($edit, 'Submit');
    $assert_session->responseContains('<em class="placeholder">datelist_min_max_time</em> must be on or before <em class="placeholder">2009-12-31 17:00:00</em>.');

    // Check datelist #date_min validation.
    $this->drupalGet('/webform/test_element_datelist');
    $edit = [
      'datelist_min_max_time[year]' => '2009',
      'datelist_min_max_time[month]' => '1',
      'datelist_min_max_time[day]' => '1',
      'datelist_min_max_time[hour]' => '8',
    ];
    $this->submitForm($edit, 'Submit');
    $assert_session->responseContains('<em class="placeholder">datelist_min_max_time</em> must be on or after <em class="placeholder">2009-01-01 09:00:00</em>.');

    // Check custom required error.
    $this->drupalGet('/webform/test_element_datelist');
    $edit = [
      'datelist_required_error[year]' => '',
      'datelist_required_error[month]' => '',
      'datelist_required_error[day]' => '',
      'datelist_required_error[hour]' => '',
      'datelist_required_error[minute]' => '',
    ];
    $this->submitForm($edit, 'Submit');
    $assert_session->responseContains('Custom required error');

    // Check that the datelist element's states is copied to the child inputs.
    $this->drupalGet('/webform/test_element_datelist');
    $assert_session->responseContains('<select data-drupal-selector="edit-datelist-conditional-required-year" title="Year" id="edit-datelist-conditional-required-year" name="datelist_conditional_required[year]" class="form-select" data-drupal-states="{&quot;required&quot;:{&quot;.webform-submission-test-element-datelist-add-form :input[name=\u0022datelist_conditional\u0022]&quot;:{&quot;checked&quot;:true}}}">');
  }

}
