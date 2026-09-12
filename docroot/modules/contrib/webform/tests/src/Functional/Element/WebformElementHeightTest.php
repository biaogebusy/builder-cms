<?php

namespace Drupal\Tests\webform\Functional\Element;

use Drupal\webform\Entity\Webform;

/**
 * Tests for webform height element.
 *
 * @group webform
 */
class WebformElementHeightTest extends WebformElementBrowserTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_element_height'];

  /**
   * Test height element.
   */
  public function testheightElement(): void {
    $assert_session = $this->assertSession();

    $webform = Webform::load('test_element_height');

    $this->drupalGet('/webform/test_element_height');

    // Check height_number_text.
    $assert_session->responseContains('<input data-drupal-selector="edit-height-number-text-feet" type="number" id="edit-height-number-text-feet" name="height_number_text[feet]" value="5" step="1" min="0" max="8" class="form-number" />');
    $assert_session->responseContains('<input data-drupal-selector="edit-height-number-text-inches" type="number" id="edit-height-number-text-inches" name="height_number_text[inches]" value="0" step="1" min="0" max="11" class="form-number" />');

    // Check height_select_text.
    $this->assertEquals('0', $assert_session->optionExists('height_select_text[feet]', '0')->getValue());
    $this->assertEquals('1', $assert_session->optionExists('height_select_text[feet]', '1')->getValue());
    $this->assertEquals('2', $assert_session->optionExists('height_select_text[feet]', '2')->getValue());
    $threeOption = $assert_session->optionExists('height_select_text[feet]', '3');
    $this->assertEquals('3', $threeOption->getValue());
    $this->assertTrue($threeOption->isSelected());
    $this->assertEquals('4', $assert_session->optionExists('height_select_text[feet]', '4')->getValue());
    $this->assertEquals('5', $assert_session->optionExists('height_select_text[feet]', '5')->getValue());
    $this->assertEquals('6', $assert_session->optionExists('height_select_text[feet]', '6')->getValue());
    $this->assertEquals('7', $assert_session->optionExists('height_select_text[feet]', '7')->getValue());
    $this->assertEquals('8', $assert_session->optionExists('height_select_text[feet]', '8')->getValue());

    $this->assertEquals('0', $assert_session->optionExists('height_select_text[inches]', '0')->getValue());
    $this->assertEquals('1', $assert_session->optionExists('height_select_text[inches]', '1')->getValue());
    $this->assertEquals('2', $assert_session->optionExists('height_select_text[inches]', '2')->getValue());
    $this->assertEquals('3', $assert_session->optionExists('height_select_text[inches]', '3')->getValue());
    $fourOption = $assert_session->optionExists('height_select_text[inches]', '4');
    $this->assertEquals('4', $fourOption->getValue());
    $this->assertTrue($fourOption->isSelected());
    $this->assertEquals('5', $assert_session->optionExists('height_select_text[inches]', '5')->getValue());
    $this->assertEquals('6', $assert_session->optionExists('height_select_text[inches]', '6')->getValue());
    $this->assertEquals('7', $assert_session->optionExists('height_select_text[inches]', '7')->getValue());
    $this->assertEquals('8', $assert_session->optionExists('height_select_text[inches]', '8')->getValue());
    $this->assertEquals('9', $assert_session->optionExists('height_select_text[inches]', '9')->getValue());
    $this->assertEquals('10', $assert_session->optionExists('height_select_text[inches]', '10')->getValue());
    $this->assertEquals('11', $assert_session->optionExists('height_select_text[inches]', '11')->getValue());

    // Check height_number_step.
    $this->assertEquals('0', $assert_session->optionExists('height_number_step[feet]', '0')->getValue());
    $this->assertEquals('1', $assert_session->optionExists('height_number_step[feet]', '1')->getValue());
    $this->assertEquals('2', $assert_session->optionExists('height_number_step[feet]', '2')->getValue());
    $this->assertEquals('3', $assert_session->optionExists('height_number_step[feet]', '3')->getValue());
    $this->assertEquals('4', $assert_session->optionExists('height_number_step[feet]', '4')->getValue());
    $fiveOption = $assert_session->optionExists('height_number_step[feet]', '5');
    $this->assertEquals('5', $fiveOption->getValue());
    $this->assertTrue($fiveOption->isSelected());
    $this->assertEquals('6', $assert_session->optionExists('height_number_step[feet]', '6')->getValue());
    $this->assertEquals('7', $assert_session->optionExists('height_number_step[feet]', '7')->getValue());
    $this->assertEquals('8', $assert_session->optionExists('height_number_step[feet]', '8')->getValue());

    $this->assertEquals('0.0', $assert_session->optionExists('height_number_step[inches]', '0.0')->getValue());
    $halfOption = $assert_session->optionExists('height_number_step[inches]', '0.5');
    $this->assertEquals('0.5', $halfOption->getValue());
    $this->assertTrue($halfOption->isSelected());
    $this->assertEquals('1.0', $assert_session->optionExists('height_number_step[inches]', '1.0')->getValue());
    $this->assertEquals('1.5', $assert_session->optionExists('height_number_step[inches]', '1.5')->getValue());
    $this->assertEquals('2.0', $assert_session->optionExists('height_number_step[inches]', '2.0')->getValue());
    $this->assertEquals('2.5', $assert_session->optionExists('height_number_step[inches]', '2.5')->getValue());
    $this->assertEquals('3.0', $assert_session->optionExists('height_number_step[inches]', '3.0')->getValue());
    $this->assertEquals('3.5', $assert_session->optionExists('height_number_step[inches]', '3.5')->getValue());
    $this->assertEquals('4.0', $assert_session->optionExists('height_number_step[inches]', '4.0')->getValue());
    $this->assertEquals('4.5', $assert_session->optionExists('height_number_step[inches]', '4.5')->getValue());
    $this->assertEquals('5.0', $assert_session->optionExists('height_number_step[inches]', '5.0')->getValue());
    $this->assertEquals('5.5', $assert_session->optionExists('height_number_step[inches]', '5.5')->getValue());
    $this->assertEquals('6.0', $assert_session->optionExists('height_number_step[inches]', '6.0')->getValue());
    $this->assertEquals('6.5', $assert_session->optionExists('height_number_step[inches]', '6.5')->getValue());
    $this->assertEquals('7.0', $assert_session->optionExists('height_number_step[inches]', '7.0')->getValue());
    $this->assertEquals('7.5', $assert_session->optionExists('height_number_step[inches]', '7.5')->getValue());
    $this->assertEquals('8.0', $assert_session->optionExists('height_number_step[inches]', '8.0')->getValue());
    $this->assertEquals('8.5', $assert_session->optionExists('height_number_step[inches]', '8.5')->getValue());
    $this->assertEquals('9.0', $assert_session->optionExists('height_number_step[inches]', '9.0')->getValue());
    $this->assertEquals('9.5', $assert_session->optionExists('height_number_step[inches]', '9.5')->getValue());
    $this->assertEquals('10.0', $assert_session->optionExists('height_number_step[inches]', '10.0')->getValue());
    $this->assertEquals('10.5', $assert_session->optionExists('height_number_step[inches]', '10.5')->getValue());
    $this->assertEquals('11.0', $assert_session->optionExists('height_number_step[inches]', '11.0')->getValue());

    // Post a submission.
    $edit = [
      'height_number_empty_required[feet]' => '5',
      'height_number_empty_required[inches]' => '5',
      'height_select_empty_required[feet]' => '5',
      'height_select_empty_required[inches]' => '5',
    ];
    $this->postSubmission($webform, $edit);

    // Check submission data.
    $assert_session->responseContains("height_number_text: '60'
height_number_symbol_required: '50'
height_select_text: '40'
height_select_text_abbreviate: '30'
height_select_symbol_required: '20'
height_select_suffix_symbol_required: '10'
height_select_suffix_text: '0'
height_select_min_max: '120'
height_number_step: '60.5'
height_number_empty: ''
height_select_empty: ''
height_number_empty_required: '65'
height_select_empty_required: '65'");

    // Check submission display.
    $assert_session->responseMatches('#<label>height_number_text</label>\s+5 feet\s+</div>#s');
    $assert_session->responseMatches('#<label>height_number_symbol_required</label>\s+4″2′\s+</div>#s');
    $assert_session->responseMatches('#<label>height_select_text</label>\s+3 feet 4 inches\s+</div>#s');
    $assert_session->responseMatches('#<label>height_select_text_abbreviate</label>\s+2 ft 6 in\s+</div>#s');
    $assert_session->responseMatches('#<label>height_select_symbol_required</label>\s+1″8′\s+</div>#s');
    $assert_session->responseMatches('#<label>height_select_suffix_symbol_required</label>\s+10′\s+</div>#s');
    $assert_session->responseMatches('#<label>height_select_min_max</label>\s+10 feet\s+</div>#s');
    $assert_session->responseMatches('#<label>height_number_step</label>\s+5 feet 0.5 inches\s+</div>#s');
    $assert_session->responseMatches('#<label>height_number_empty_required</label>\s+5 feet 5 inches\s+</div>#s');
    $assert_session->responseMatches('#<label>height_select_empty_required</label>\s+5 feet 5 inches\s+</div>#s');

    $assert_session->responseNotContains('<label>height_select_suffix_text</label>');
    $assert_session->responseNotContains('<label>height_number_empty</label>');
    $assert_session->responseNotContains('<label>height_select_empty</label>');
  }

}
