<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\Functional;

use Drupal\Tests\better_exposed_filters\Traits\BetterExposedFiltersTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Views;

/**
 * Test that the reset button works.
 *
 * @group better_exposed_filters
 */
class ResetButtonTest extends BrowserTestBase {
  use BetterExposedFiltersTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'bef_test',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Reset button should work on an autocomplete field with an invalid value.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testResetWorksWithAutocomplete(): void {
    // Setup: Prepare invalid data and expected error message used in this test.
    $invalidData = 'Wwww';
    $expectedErrorMessage = \sprintf('There are no taxonomy terms matching "%s".', $invalidData);

    // Setup: Change the field_bef_location exposed filter to autocomplete.
    $view = Views::getView('bef_test');
    $view->setDisplay();
    $filters = $view->displayHandlers->get('default')->getOption('filters');
    $filters['field_bef_location_target_id']['type'] = 'textfield';
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);
    $view->storage->enable()->save();

    // Setup: Enable the BEF reset button.
    $this->setBetterExposedOptions($view, ['reset_button' => TRUE]);

    // Setup: Navigate to the view. Submit an invalid value in the autocomplete.
    $this->drupalGet('/bef-test');
    $this->submitForm([
      'field_bef_location_target_id' => $invalidData,
    ], 'Apply');

    // Assert: There is an error message about the invalid value; and the
    // invalid value is present in the autocomplete field.
    $this->assertSession()->statusMessageContains($expectedErrorMessage, 'error');
    $this->assertSession()->fieldValueEquals('field_bef_location_target_id', $invalidData);

    // SUT: Click the reset button.
    $this->submitForm([], 'Reset');

    // Assert: There is no longer an error message about the invalid value. The
    // invalid value is no longer present in the autocomplete field.
    $this->assertSession()->statusMessageNotContains($expectedErrorMessage, 'error');
    $this->assertSession()->fieldValueEquals('field_bef_location_target_id', '');
  }

}
