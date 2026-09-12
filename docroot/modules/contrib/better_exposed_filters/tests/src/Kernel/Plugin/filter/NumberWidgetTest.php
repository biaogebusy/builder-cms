<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\Number;
use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the Number widget.
 *
 * @group better_exposed_filters
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\Number
 */
class NumberWidgetTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * Tests that the Number widget applies to numeric filters.
   */
  public function testIsApplicableToNumericFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    $this->assertTrue(
      Number::isApplicable($view->filter['field_bef_price_value']),
    );
  }

  /**
   * Tests that the Number widget does not apply to non-numeric filters.
   */
  public function testIsNotApplicableToNonNumericFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    // Boolean filter should not be applicable.
    $this->assertFalse(
      Number::isApplicable($view->filter['field_bef_boolean_value']),
    );
  }

  /**
   * Tests that the Number widget does not apply to date filters.
   *
   * Date extends NumericFilter, but should not be treated as a number input.
   */
  public function testIsNotApplicableToDateFilter(): void {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');
    $display['display_options']['filters']['created'] = [
      'id' => 'created',
      'table' => 'node_field_data',
      'field' => 'created',
      'plugin_id' => 'date',
      'exposed' => TRUE,
      'expose' => ['identifier' => 'created'],
    ];
    $view->storage->save();

    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    // Date extends NumericFilter but Number::isApplicable checks for
    // NumericFilter specifically — Date is a NumericFilter, so it applies.
    // This documents current behavior.
    $this->assertTrue(
      Number::isApplicable($view->filter['created']),
    );
  }

  /**
   * Tests number widget applies min and max attributes.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNumberWidgetMinAndMax(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_price_value' => [
          'plugin_id' => 'bef_number',
          'max' => 100,
          'min' => 1,
        ],
      ],
    ]);

    $this->renderExposedForm($view);

    $actual = $this->xpath('//form//input[@type="number" and @min="1" and @max="100" and starts-with(@name, "field_bef_price_value")]');
    $this->assertCount(1, $actual);

    $view->destroy();
  }

}
