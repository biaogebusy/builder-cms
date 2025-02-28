<?php

namespace Drupal\Tests\webform_image_select\Functional;

use Drupal\Tests\webform\Functional\Element\WebformElementBrowserTestBase;
use Drupal\webform\Entity\Webform;

/**
 * Tests for webform image select element.
 *
 * @group webform_image_select
 */
class WebformImageSelectElementTest extends WebformElementBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['webform', 'webform_image_select', 'webform_image_select_test'];

  /**
   * Test webform image select element.
   */
  public function testImageSelect() {
    $assert_session = $this->assertSession();

    $this->drupalGet('/webform/test_element_image_select');

    // Check rendering of image select with required.
    $assert_session->responseContains('<select data-drupal-selector="edit-image-select-default" data-images="{&quot;kitten_1&quot;:{&quot;text&quot;:&quot;Cute Kitten 1&quot;,&quot;src&quot;:&quot;http:\/\/placekitten.com\/220\/200&quot;},&quot;kitten_2&quot;:{&quot;text&quot;:&quot;Cute Kitten 2&quot;,&quot;src&quot;:&quot;http:\/\/placekitten.com\/180\/200&quot;},&quot;kitten_3&quot;:{&quot;text&quot;:&quot;Cute Kitten 3&quot;,&quot;src&quot;:&quot;http:\/\/placekitten.com\/130\/200&quot;},&quot;kitten_4&quot;:{&quot;text&quot;:&quot;Cute Kitten 4&quot;,&quot;src&quot;:&quot;http:\/\/placekitten.com\/270\/200&quot;}}" class="webform-image-select js-webform-image-select form-select required" id="edit-image-select-default" name="image_select_default" required="required" aria-required="true">');

    // Check rendering of image select with limit.
    $assert_session->responseContains('<select data-limit="2" data-drupal-selector="edit-image-select-limit" data-images="{&quot;kitten_1&quot;:{&quot;text&quot;:&quot;Cute Kitten 1&quot;,&quot;src&quot;:&quot;http:\/\/placekitten.com\/220\/200&quot;},&quot;kitten_2&quot;:{&quot;text&quot;:&quot;Cute Kitten 2&quot;,&quot;src&quot;:&quot;http:\/\/placekitten.com\/180\/200&quot;},&quot;kitten_3&quot;:{&quot;text&quot;:&quot;Cute Kitten 3&quot;,&quot;src&quot;:&quot;http:\/\/placekitten.com\/130\/200&quot;},&quot;kitten_4&quot;:{&quot;text&quot;:&quot;Cute Kitten 4&quot;,&quot;src&quot;:&quot;http:\/\/placekitten.com\/270\/200&quot;}}" class="webform-image-select js-webform-image-select form-select" multiple="multiple" name="image_select_limit[]" id="edit-image-select-limit">');

    // Check rendering of image select with HTML markup and XSS test.
    $assert_session->responseContains('<select data-drupal-selector="edit-image-select-html" data-show-label="data-show-label" data-images="{&quot;\u003C1\u003E&quot;:{&quot;text&quot;:&quot;Cute \u003Cb\u003EKitten\u003C\/b\u003E 1&quot;,&quot;src&quot;:&quot;http:\/\/placekitten.com\/220\/200&quot;},&quot;\u00222\u0022&quot;:{&quot;text&quot;:&quot;Cute \u003Cem\u003EKitten\u003C\/em\u003E 2&quot;,&quot;src&quot;:&quot;http:\/\/placekitten.com\/180\/200&quot;},&quot;\u00263&quot;:{&quot;text&quot;:&quot;Cute \u003Cu\u003EKitten\u003C\/u\u003E 3&quot;,&quot;src&quot;:&quot;http:\/\/placekitten.com\/130\/200&quot;},&quot;4&quot;:{&quot;text&quot;:&quot;Cute Kitten 4 alert(\u0022XSS\u0022);&quot;,&quot;src&quot;:&quot;http:\/\/placekitten.com\/270\/200&quot;}}" class="webform-image-select js-webform-image-select form-select" id="edit-image-select-html" name="image_select_html"><option value="" selected="selected">- None -</option><option value="&lt;1&gt;">Cute Kitten 1</option><option value="&quot;2&quot;">Cute Kitten 2</option><option value="&amp;3">Cute Kitten 3</option><option value="4">Cute Kitten 4 alert(&quot;XSS&quot;);</option></select>');

    // Check rendering with filter.
    $assert_session->responseContains('<input class="webform-form-filter-text form-search" data-focus="false" data-item-singlular="animal" data-item-plural="animals" data-summary=".js-image-select-filter-custom-filter .webform-image-select-summary" data-no-results=".js-image-select-filter-custom-filter .webform-image-select-no-results" data-element=".js-image-select-filter-custom-filter .thumbnails" data-source=".thumbnail p" data-parent="li" data-selected=".selected" title="Enter a keyword to filter by." type="search" id="edit-image-select-filter-custom-filter" name="image_select_filter_custom_filter" size="30" maxlength="128" placeholder="Find an animal" />');
    $assert_session->responseContains('<span class="field-suffix"><span class="webform-image-select-summary">8 animals</span>');
    $assert_session->responseContains('<div style="display:none" class="webform-image-select-no-results webform-message js-webform-message">');
    $assert_session->responseContains('No animals found.');

    // Check preview.
    $webform = Webform::load('test_element_image_select');
    $edit = [
      'image_select_default' => 'kitten_1',
    ];
    $this->postSubmission($webform, $edit, 'Preview');
    $assert_session->responseContains('<figure style="display: inline-block; margin: 0 6px 6px 0; padding: 6px; border: 1px solid #ddd;width: 220px"><img src="http://placekitten.com/220/200" width="220" height="200" alt="Cute Kitten 1" title="Cute Kitten 1" loading="lazy" />');
  }

}
