<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\Single;
use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Views;

/**
 * Tests the Single on/off filter widget.
 *
 * @group better_exposed_filters
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\Single
 */
class SingleFilterWidgetKernelTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * Tests that the Single widget applies to boolean filters.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testIsApplicableToBooleanFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    $this->assertTrue(
      Single::isApplicable($view->filter['field_bef_boolean_value']),
    );
  }

  /**
   * Tests that the Single widget does not apply to non-boolean filters.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testIsNotApplicableToNonBooleanFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    $this->assertFalse(
      Single::isApplicable($view->filter['field_bef_integer_value']),
    );
  }

  /**
   * Tests that the Single widget does not apply to null filters.
   */
  public function testIsNotApplicableToNullFilter(): void {
    $this->assertFalse(Single::isApplicable(NULL));
  }

  /**
   * Tests that the Single widget applies to single-item grouped filters.
   */
  public function testIsApplicableToSingleItemGroupedFilter(): void {
    $mock = $this->createMock(FilterPluginBase::class);
    $mock->method('isAGroup')->willReturn(TRUE);
    $mock->options = [
      'group_info' => [
        'group_items' => ['item1'],
      ],
    ];

    $this->assertTrue(Single::isApplicable($mock));
  }

  /**
   * Tests that the Single widget does not apply to multi-item grouped filters.
   */
  public function testIsNotApplicableToMultiItemGroupedFilter(): void {
    $mock = $this->createMock(FilterPluginBase::class);
    $mock->method('isAGroup')->willReturn(TRUE);
    $mock->options = [
      'group_info' => [
        'group_items' => ['item1', 'item2'],
      ],
    ];

    $this->assertFalse(Single::isApplicable($mock));
  }

  /**
   * Tests rendering as a single checkbox.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSingleExposedCheckbox(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_boolean_value' => [
          'plugin_id' => 'bef_single',
        ],
      ],
    ]);

    $this->renderExposedForm($view);

    $actual = $this->xpath('//form//input[@type="checkbox" and starts-with(@name, "field_bef_boolean_value")]');
    $this->assertCount(1, $actual, 'Exposed filter "FIELD_BEF_BOOLEAN" is rendered as a checkbox.');

    $view->destroy();
  }

  /**
   * Tests that no hidden field is rendered alongside the checkbox.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSingleExposedCheckboxNoHiddenField(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_boolean_value' => [
          'plugin_id' => 'bef_single',
        ],
      ],
    ]);

    $this->renderExposedForm($view);

    $checkbox = $this->xpath('//form//input[@type="checkbox" and starts-with(@name, "field_bef_boolean_value")]');
    $this->assertCount(1, $checkbox);

    $hidden = $this->xpath('//form//input[@type="hidden" and starts-with(@name, "field_bef_boolean_value")]');
    $this->assertCount(0, $hidden);

    $view->destroy();
  }

}
