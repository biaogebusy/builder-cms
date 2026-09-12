<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\Sliders;
use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the Sliders filter widget.
 *
 * @group better_exposed_filters
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\Sliders
 */
class SlidersFilterWidgetKernelTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * Tests that the Sliders widget applies to numeric filters.
   */
  public function testIsApplicableToNumericFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    $this->assertTrue(
      Sliders::isApplicable($view->filter['field_bef_price_value']),
    );
  }

  /**
   * Tests that the Sliders widget does not apply to date filters.
   *
   * Date extends NumericFilter but should not be treated as a slider.
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

    $this->assertFalse(
      Sliders::isApplicable($view->filter['created']),
    );
  }

  /**
   * Tests that the Sliders widget does not apply to non-numeric filters.
   */
  public function testIsNotApplicableToNonNumericFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    $this->assertFalse(
      Sliders::isApplicable($view->filter['field_bef_boolean_value']),
    );
  }

  /**
   * Tests that the Sliders widget does not apply to grouped filters.
   */
  public function testIsNotApplicableToGroupedFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    $filter = $view->filter['field_bef_price_value'];
    $filter->options['is_grouped'] = TRUE;

    $this->assertFalse(Sliders::isApplicable($filter));
  }

}
