<?php

namespace Drupal\Tests\webform\FunctionalJavascript\Element;

use Drupal\Tests\webform\FunctionalJavascript\WebformWebDriverTestBase;
use Drupal\webform\Entity\Webform;

/**
 * Tests webform computed element Ajax support.
 *
 * @see \Drupal\Tests\ajax_example\FunctionalJavascript\AjaxWizardTest
 *
 * @group webform_javascript
 */
class WebformElementComputedJavaScriptTest extends WebformWebDriverTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = [
    'test_element_computed_ajax',
  ];

  /**
   * Tests computed element Ajax.
   */
  public function testComputedElementAjax(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    $webform = Webform::load('test_element_computed_ajax');

    /* ********************************************************************** */

    // Check computed Twig element a and b elements exist.
    $this->drupalGet($webform->toUrl());
    $assert_session->fieldExists('a[select]');
    $assert_session->fieldExists('b');
    $assert_session->buttonExists('webform-computed-webform_computed_twig-button');
    $assert_session->hiddenFieldValueEquals('webform_computed_twig', 'Please enter a value for a and b.');

    // Calculate computed Twig element. Fill b first because
    // fillField does not fire a change event on number fields.
    // selectFieldOption fires change which triggers the computed
    // element's 500ms debounce AJAX.
    $random = rand(1, 9);
    $page->fillField('b', $random);
    $page->selectFieldOption('a[select]', '1');

    // Wait for the debounce-triggered AJAX to update the specific
    // hidden field with the computed value.
    $result = $session->wait(5000, "document.querySelector('input[name=\"webform_computed_twig\"]').value === '1 + " . $random . ' = ' . ($random + 1) . "'");
    $this->assertTrue($result, 'Computed Twig value was not updated via AJAX.');
  }

}
