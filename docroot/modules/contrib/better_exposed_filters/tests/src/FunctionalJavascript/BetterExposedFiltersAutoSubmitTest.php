<?php

namespace Drupal\Tests\better_exposed_filters\FunctionalJavascript;

use Drupal\views\Views;

/**
 * Tests the auto submit functionality of better exposed filters.
 *
 * @group better_exposed_filters
 */
class BetterExposedFiltersAutoSubmitTest extends BetterExposedFiltersTestBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $view = Views::getView('bef_test');
    $this->setBetterExposedOptions($view, [
      'general' => [
        'autosubmit' => TRUE,
        'autosubmit_exclude_textfield' => TRUE,
      ],
    ]);

    // Create a few test nodes.
    $this->createNode([
      'title' => 'Page One',
      'field_bef_price' => '10',
      'field_bef_letters' => 'a',
      'type' => 'bef_test',
      'created' => strtotime('-2 days'),
    ]);
    $this->createNode([
      'title' => 'Page Two',
      'field_bef_price' => '75',
      'field_bef_letters' => 'b',
      'type' => 'bef_test',
      'created' => strtotime('-3 days'),
    ]);
  }

  /**
   * Tests if filtering via auto-submit works with a selected breakpoint.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testAutoSubmitBreakpoint(): void {
    $view = Views::getView('bef_test');

    // Enable auto-submit, but disable for text fields.
    $this->setBetterExposedOptions($view, [
      'general' => [
        'autosubmit' => TRUE,
        'autosubmit_exclude_textfield' => FALSE,
        'autosubmit_breakpoint' => 'bef_test:bef_test.test',
      ],
    ]);

    $session = $this->getSession();
    // Prepare window.
    $session->resizeWindow(500, 500);

    // Visit the bef-test page.
    $this->drupalGet('bef-test');
    $page = $session->getPage();

    // Ensure that the content we're testing for is present.
    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextContains('Page Two');

    // Enter value in the email field.
    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    /* Assert exposed operator field does not have attribute to exclude it from
    auto-submit. */
    $field_bef_exposed_operator_email = $page->find('css', '.form-item-field-bef-email-value input');
    $this->assertFalse($field_bef_exposed_operator_email->hasAttribute('data-bef-auto-submit-exclude'));
    $field_bef_email->setValue('1bef');

    // Verify that auto submit didn't run, due to breakpoint.
    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextContains('Page Two');

    // Prepare window.
    $session->resizeWindow(1000, 1000);

    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    $field_bef_email->setValue('1bef');
    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextNotContains('Page Two');
  }

  /**
   * Tests if filtering via auto-submit works.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAutoSubmitMinLength(): void {
    $view = Views::getView('bef_test');

    // Enable auto-submit, but disable for text fields.
    $this->setBetterExposedOptions($view, [
      'general' => [
        'autosubmit_exclude_textfield' => FALSE,
        'autosubmit_textfield_minimum_length' => 3,
      ],
    ]);

    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    // Ensure that the content we're testing for is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    // Enter value in email field.
    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    /* Assert exposed operator field does not have attribute to exclude it from
    auto-submit. */
    $field_bef_exposed_operator_email = $page->find('css', '.form-item-field-bef-email-value input');
    $this->assertFalse($field_bef_exposed_operator_email->hasAttribute('data-bef-auto-submit-exclude'));
    $field_bef_email->setValue('1');
    // Verify that auto submit didn't run, due to less than 4 characters.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    $field_bef_email->setValue('1bef');
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);
  }

  /**
   * Tests if filtering via auto-submit works.
   */
  public function testAutoSubmit(): void {
    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    // Ensure that the content we're testing for is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    // Search for "Page One".
    $field_bef_integer = $page->findField('field_bef_integer_value');
    $field_bef_integer->setValue('1');

    // Verify that only the "Page One" Node is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);

    // Enter value in email field.
    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    $field_bef_email->setValue('qwerty@test.com');

    // Enter value in exposed operator email field.
    $field_bef_exposed_operator_email = $page->find('css', '.form-item-field-bef-email-value input');
    $field_bef_exposed_operator_email->setValue('qwerty@test.com');

    // Verify nothing has changed.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);

    // Submit form.
    $this->submitForm([], 'Apply');

    // Verify no results are visible.
    $html = $page->getHtml();
    $this->assertStringNotContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);
  }

  /**
   * Tests if filtering via auto-submit works if exposed form is a block.
   */
  public function testAutoSubmitWithExposedFormBlock() {
    $this->drupalPlaceBlock('views_exposed_filter_block:bef_test-page_2');

    // Visit the bef-test page.
    $this->drupalGet('bef-test-with-block');

    $session = $this->getSession();
    $page = $session->getPage();

    // Ensure that the content we're testing for is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    // Search for "Page One".
    $field_bef_integer = $page->findField('field_bef_integer_value');
    $field_bef_integer->setValue('1');
    $field_bef_integer->blur();

    // Verify that only the "Page One" Node is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);

    // Enter value in email field.
    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    $field_bef_email->setValue('qwerty@test.com');

    // Enter value in exposed operator email field.
    $field_bef_exposed_operator_email = $page->find('css', '.form-item-field-bef-email-value input');
    $field_bef_exposed_operator_email->setValue('qwerty@test.com');

    // Verify nothing has changed.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);

    // Submit form.
    $this->submitForm([], 'Apply');

    // Verify no results are visible.
    $html = $page->getHtml();
    $this->assertStringNotContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);
  }

  /**
   * Tests data-bef-auto-submit-exclude persists after AJAX with block form.
   *
   * When the exposed form is rendered as a block, the 'bef-exposed-form' class
   * is on the block wrapper, not the form element. After AJAX updates, the
   * behavior must reapply the data-bef-auto-submit-exclude attribute to text
   * fields.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAutoSubmitExcludeTextfieldPersistsAfterAjaxWithBlock(): void {
    $this->turnAjaxOn();
    $view = Views::getView('bef_test');
    $this->drupalPlaceBlock('views_exposed_filter_block:bef_test-page_2');

    $this->setBetterExposedOptions($view, [
      'general' => [
        'autosubmit' => TRUE,
        'autosubmit_exclude_textfield' => TRUE,
      ],
    ]);

    $this->drupalGet('bef-test-with-block');

    $session = $this->getSession();
    $page = $session->getPage();

    // Verify both nodes are visible.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    // Assert data-bef-auto-submit-exclude is present on text fields on
    // initial page load. Use JavaScript to check the actual DOM attribute
    // as Mink may cache stale element references.
    $has_attr_before = $session->evaluateScript(
      "document.querySelector('.form-item-field-bef-email-value input[type=\"text\"]')?.hasAttribute('data-bef-auto-submit-exclude') ?? false"
    );
    $this->assertTrue($has_attr_before);

    // Trigger an AJAX refresh by changing a non-text filter (integer select).
    $field_bef_integer = $page->findField('field_bef_integer_value');
    $field_bef_integer->setValue('1');
    $field_bef_integer->blur();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify the view filtered correctly.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);

    // After AJAX, simulate the exact condition that triggers the bug: call
    // Drupal.attachBehaviors with the FORM element as context, mimicking what
    // happens during AJAX form replacement in a block. The once() on the
    // .bef-exposed-form wrapper prevents re-processing when context is the
    // form, because the wrapper (not the form) has the once data attribute.
    $session->evaluateScript(
      "document.querySelectorAll('.form-item-field-bef-email-value input[type=\"text\"]').forEach(el => { el.removeAttribute('data-bef-auto-submit-exclude'); el.removeAttribute('data-once'); })"
    );

    // Verify removal worked.
    $has_attr_removed = $session->evaluateScript(
      "document.querySelector('.form-item-field-bef-email-value input[type=\"text\"]')?.hasAttribute('data-bef-auto-submit-exclude') ?? false"
    );
    $this->assertFalse($has_attr_removed);

    $session->evaluateScript(
      "(function() { const form = document.querySelector('form[id*=\"views-exposed-form\"]'); if (form) { Drupal.attachBehaviors(form, drupalSettings); } })()"
    );

    $has_attr_after = $session->evaluateScript(
      "document.querySelector('.form-item-field-bef-email-value input[type=\"text\"]')?.hasAttribute('data-bef-auto-submit-exclude') ?? false"
    );
    $this->assertTrue($has_attr_after);
  }

  /**
   * Tests auto submit with checkboxes.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAutoSubmitWithCheckboxes(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef',
        ],
      ],
    ]);

    // Visit the bef-test page.
    $this->drupalGet('/bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextContains('Page Two');

    $page->checkField('edit-field-bef-letters-value-a');
    $page->pressButton('Apply');

    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextNotContains('Page Two');
  }

  /**
   * Tests that auto submit select elements work and gain focus on ajax reload.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testAutoSubmitWithSelect(): void {
    $this->turnAjaxOn();

    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    $field_bef_select = $page->find('css', 'select#edit-term-node-tid-depth');
    $field_bef_select->setValue('15');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->pageTextContains('Page Two');
    $this->assertSession()->pageTextNotContains('Page One');
    $active_selector = $this->getSession()->evaluateScript('document.activeElement.getAttribute("data-drupal-selector")');
    $this->assertEquals('edit-term-node-tid-depth', $active_selector, 'Element with correct data-drupal-selector has focus.');
  }

  /**
   * Tests that auto submit radio buttons work and gain focus on ajax reload.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testAutoSubmitWithRadios(): void {

    $view = Views::getView('bef_test');

    // Convert filter to radios by setting multiple = FALSE.
    $view->storage->getDisplay('default')['display_options']['filters']['field_bef_letters_value']['expose']['multiple'] = FALSE;
    $view->storage->save();

    // Configure BEF settings for the filter.
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef',
        ],
      ],
    ]);

    $this->turnAjaxOn();

    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextContains('Page Two');

    // Select radio button for 'b' to filter to only "Page Two".
    $radio_b = $page->find('css', 'input[name="field_bef_letters_value"][value="b"]');
    $this->assertNotNull($radio_b, 'Radio button for value "b" should exist');

    // Trigger the radio button change and auto-submit.
    $radio_b->click();

    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->pageTextContains('Page Two');
    $this->assertSession()->pageTextNotContains('Page One');

    $this->assertTrue($radio_b->isChecked(), 'Radio button for value "b" should be selected');

    // Check focus restoration.
    $active_selector = $this->getSession()->evaluateScript('document.activeElement.getAttribute("data-drupal-selector")');
    $expected_selector = $radio_b->getAttribute('data-drupal-selector');
    $this->assertEquals($expected_selector, $active_selector, 'The clicked radio button should regain focus after AJAX submission.');

  }

  /**
   * Tests that auto-submit text fields retain focus and cursor position.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAutoSubmitTextFieldFocusRetention(): void {
    $view = Views::getView('bef_test');
    $this->setBetterExposedOptions($view, [
      'general' => [
        'autosubmit_exclude_textfield' => FALSE,
      ],
    ]);

    $this->turnAjaxOn();

    $this->drupalGet('bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    // Find the text input field.
    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    $this->assertNotNull($field_bef_email);

    // Use JavaScript to set value, store the triggered selector (as
    // triggerSubmit would), and click submit to trigger AJAX.
    $session->executeScript("
      var input = document.querySelector('.form-item-field-bef-email-value input');
      input.value = '1bef';
      input.focus();
      // Store the selector as triggerSubmit() would.
      drupalSettings.bef_autosubmit_triggered_selector = input.getAttribute('data-drupal-selector');
      drupalSettings.bef_autosubmit_cursor_position = input.value.length;
      // Click the auto-submit button to trigger immediate AJAX.
      document.querySelector('[data-bef-auto-submit-click]').click();
    ");

    $this->assertSession()->assertWaitOnAjaxRequest();

    // Allow the IntersectionObserver to fire.
    $this->getSession()->wait(500);

    // Check focus restoration - the text field should regain focus.
    $active_selector = $session->evaluateScript('document.activeElement.getAttribute("data-drupal-selector")');
    $this->assertIsString($active_selector);
    $expected_selector_base = 'edit-field-bef-email-value';
    $this->assertStringStartsWith($expected_selector_base, $active_selector);

    // Set a value and position the cursor in the middle.
    $test_value = 'testvalue';
    $cursor_position = 4;

    // Use JavaScript to set value, position cursor, store the triggered
    // selector, and click submit to trigger immediate AJAX.
    $session->executeScript("
      var input = document.querySelector('.form-item-field-bef-email-value input');
      input.value = '{$test_value}';
      input.focus();
      input.setSelectionRange({$cursor_position}, {$cursor_position});
      // Store the selector and cursor position as triggerSubmit() would.
      drupalSettings.bef_autosubmit_triggered_selector = input.getAttribute('data-drupal-selector');
      drupalSettings.bef_autosubmit_cursor_position = {$cursor_position};
      // Click the auto-submit button to trigger immediate AJAX.
      document.querySelector('[data-bef-auto-submit-click]').click();
    ");

    // Wait for AJAX to complete.
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Allow the IntersectionObserver to fire.
    $this->getSession()->wait(500);

    // Check that the cursor position was restored after AJAX.
    $restored_position = $session->evaluateScript('document.activeElement.selectionStart');
    $this->assertEquals($cursor_position, $restored_position);
  }

  /**
   * Tests auto submit sort only.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testAutoSubmitSortOnly(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'general' => [
        'autosubmit' => TRUE,
        'auto_submit_sort_only' => TRUE,
        'autosubmit_exclude_textfield' => FALSE,
        'autosubmit_textfield_minimum_length' => 3,
      ],
    ]);

    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    // Ensure that the content we're testing for is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    // This should trigger nothing.
    $field_bef_integer = $page->findField('field_bef_integer_value');
    $field_bef_integer->setValue('1');
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);
    $field_bef_integer->setValue('All');

    // Change sort.
    $page->selectFieldOption('sort_order', 'ASC');
    $cells = $this->xpath('//table/tbody/tr/td[1]');
    $values = array_map(fn($cell) => $cell->getText(), $cells);

    // Now check the expected order.
    $this->assertEquals('Page Two', $values[0]);
    $this->assertEquals('Page One', $values[1]);
  }

}
