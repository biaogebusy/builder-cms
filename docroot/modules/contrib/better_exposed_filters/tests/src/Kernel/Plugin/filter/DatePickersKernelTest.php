<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\DatePickers;
use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Tests the DatePickers filter widget.
 *
 * @group better_exposed_filters
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\DatePickers
 */
class DatePickersKernelTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * Returns a base filter config for the node created date.
   *
   * @param array $overrides
   *   Values to merge into the base config.
   *
   * @return array
   *   Filter config array.
   */
  private function baseFilterConfig(array $overrides = []): array {
    return array_merge([
      'id' => 'created',
      'table' => 'node_field_data',
      'field' => 'created',
      'plugin_id' => 'date',
      'exposed' => TRUE,
      'expose' => ['identifier' => 'created'],
    ], $overrides);
  }

  /**
   * Saves a filter and BEF plugin to the view and returns it uninitialized.
   *
   * @param array $filter_config
   *   Filter config to inject into the view display.
   *
   * @return \Drupal\views\ViewExecutable
   *   Uninitialized view ready for getExposedFormRenderArray().
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function viewWithBef(array $filter_config): ViewExecutable {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');
    $display['display_options']['filters']['created'] = $filter_config;
    $view->storage->save();

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'created' => ['plugin_id' => 'bef_datepicker'],
      ],
    ]);

    return Views::getView('bef_test');
  }

  /**
   * Saves a filter to the view and returns a fully initialized view.
   *
   * @param array $filter_config
   *   Filter config to inject into the view display.
   *
   * @return \Drupal\views\ViewExecutable
   *   Initialized view with handlers loaded.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function initializedViewWithFilter(array $filter_config): ViewExecutable {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');
    $display['display_options']['filters']['created'] = $filter_config;
    $view->storage->save();

    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();
    return $view;
  }

  /**
   * Locates the date element, handling Views wrapper nesting.
   *
   * @param array $output
   *   Exposed form render array.
   *
   * @return array
   *   The date filter element.
   */
  private function findElement(array $output): array {
    $id = 'created';
    $wrapper = $id . '_wrapper';
    if (isset($output[$wrapper][$wrapper][$id])) {
      return $output[$wrapper][$wrapper][$id];
    }
    if (isset($output[$wrapper][$id])) {
      return $output[$wrapper][$id];
    }
    if (isset($output[$id])) {
      return $output[$id];
    }
    $this->fail("Could not find element for '$id' in the exposed form output.");
  }

  /**
   * Tests isApplicable() returns TRUE for a Date filter.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testIsApplicable(): void {
    $view = $this->initializedViewWithFilter($this->baseFilterConfig());

    $this->assertTrue(
      DatePickers::isApplicable($view->filter['created'])
    );
  }

  /**
   * Tests that grouped filters are not applicable.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testIsNotApplicableToGroupedFilters(): void {
    $view = $this->initializedViewWithFilter($this->baseFilterConfig());
    $filter = $view->filter['created'];
    $filter->options['is_grouped'] = TRUE;

    $this->assertFalse(DatePickers::isApplicable($filter));
  }

  /**
   * Tests that non-date filters are not applicable.
   */
  public function testIsNotApplicableToNonDateFilters(): void {
    $mock = $this->createMock(FilterPluginBase::class);
    $mock->expects($this->any())->method('isAGroup')->willReturn(FALSE);

    $this->assertFalse(DatePickers::isApplicable($mock));
  }

  /**
   * Tests single date element has bef-datepicker class.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSingleDateHasDatepickerClass(): void {
    $view = $this->viewWithBef($this->baseFilterConfig(['operator' => '>']));

    $output = $this->getExposedFormRenderArray($view);
    $element = $output['created'];

    $this->assertEquals('date', $element['#type']);
    $this->assertContains('bef-datepicker', $element['#attributes']['class']);
    $this->assertEquals('off', $element['#attributes']['autocomplete']);
  }

  /**
   * Tests between date elements have bef-datepicker class.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBetweenDatesHaveDatepickerClass(): void {
    $view = $this->viewWithBef($this->baseFilterConfig([
      'operator' => 'between',
    ]));

    $output = $this->getExposedFormRenderArray($view);
    $element = $this->findElement($output);

    $this->assertEquals('date', $element['min']['#type']);
    $this->assertEquals('date', $element['max']['#type']);
    $this->assertContains('bef-datepicker', $element['min']['#attributes']['class']);
    $this->assertContains('bef-datepicker', $element['max']['#attributes']['class']);
  }

  /**
   * Tests exposed operator with a single-value operator has bef-datepicker.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testExposedOperatorSingleValueHasDatepickerClass(): void {
    $view = $this->viewWithBef($this->baseFilterConfig([
      'operator' => '>',
      'expose' => [
        'identifier' => 'created',
        'use_operator' => TRUE,
        'operator_id' => 'created_op',
      ],
    ]));

    $output = $this->getExposedFormRenderArray($view);
    $element = $this->findElement($output);

    // The value sub-element should have bef-datepicker.
    $this->assertEquals('date', $element['value']['#type']);
    $this->assertContains('bef-datepicker', $element['value']['#attributes']['class']);
    $this->assertEquals('off', $element['value']['#attributes']['autocomplete']);
  }

  /**
   * Tests exposed operator also applies bef-datepicker to min/max.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testExposedOperatorMinMaxHaveDatepickerClass(): void {
    $view = $this->viewWithBef($this->baseFilterConfig([
      'operator' => 'between',
      'expose' => [
        'identifier' => 'created',
        'use_operator' => TRUE,
        'operator_id' => 'created_op',
      ],
    ]));

    $output = $this->getExposedFormRenderArray($view);
    $element = $this->findElement($output);

    // Both min/max and value should have bef-datepicker.
    $this->assertEquals('date', $element['min']['#type']);
    $this->assertEquals('date', $element['max']['#type']);
    $this->assertContains('bef-datepicker', $element['min']['#attributes']['class']);
    $this->assertContains('bef-datepicker', $element['max']['#attributes']['class']);

    // The value sub-element should also be converted.
    $this->assertEquals('date', $element['value']['#type']);
    $this->assertContains('bef-datepicker', $element['value']['#attributes']['class']);
  }

}
