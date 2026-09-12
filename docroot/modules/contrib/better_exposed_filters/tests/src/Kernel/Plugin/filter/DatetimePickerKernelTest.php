<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\DatetimePicker;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Tests the DatetimePicker filter widget.
 *
 * @group better_exposed_filters
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\DatetimePicker
 */
class DatetimePickerKernelTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'views',
    'node',
    'filter',
    'options',
    'text',
    'taxonomy',
    'datetime',
    'user',
    'better_exposed_filters',
    'bef_test',
  ];

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->installEntitySchema('user');

    FieldStorageConfig::create([
      'field_name' => 'field_bef_datetime',
      'type' => 'datetime',
      'entity_type' => 'node',
      'settings' => ['datetime_type' => 'datetime'],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_bef_datetime',
      'entity_type' => 'node',
      'bundle' => 'bef_test',
    ])->save();
  }

  /**
   * Returns a base filter config for the datetime field.
   *
   * @param array $overrides
   *   Values to merge into the base config.
   *
   * @return array
   *   Filter config array.
   */
  private function baseFilterConfig(array $overrides = []): array {
    return array_merge([
      'id' => 'field_bef_datetime_value',
      'table' => 'node__field_bef_datetime',
      'field' => 'field_bef_datetime_value',
      'plugin_id' => 'datetime',
      'exposed' => TRUE,
      'expose' => ['identifier' => 'field_bef_datetime_value'],
    ], $overrides);
  }

  /**
   * Saves a filter + BEF plugin to the view and returns it uninitialized.
   *
   * The view is intentionally left uninitialized so that
   * getExposedFormRenderArray() triggers a fresh initDisplay() that picks up
   * both the filter config and the BEF plugin settings.
   *
   * @param array $filter_config
   *   Filter config to inject into the view display.
   *
   * @return \Drupal\views\ViewExecutable
   *   Uninitialized view ready for getExposedFormRenderArray().
   */
  private function viewWithBef(array $filter_config): ViewExecutable {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');
    $display['display_options']['filters']['field_bef_datetime_value'] = $filter_config;
    $view->storage->save();

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_datetime_value' => ['plugin_id' => 'bef_datetimepicker'],
      ],
    ]);

    return Views::getView('bef_test');
  }

  /**
   * Saves a filter to the view and returns a fully initialized view.
   *
   * Used for isApplicable() tests that need $view->filter populated.
   *
   * @param array $filter_config
   *   Filter config to inject into the view display.
   *
   * @return \Drupal\views\ViewExecutable
   *   Initialized view with handlers loaded.
   */
  private function initializedViewWithFilter(array $filter_config): ViewExecutable {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');
    $display['display_options']['filters']['field_bef_datetime_value'] = $filter_config;
    $view->storage->save();

    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();
    return $view;
  }

  /**
   * Locates the between-operator element, handling Views wrapper nesting.
   *
   * @param array $output
   *   Exposed form render array.
   *
   * @return array
   *   The element containing 'min' and 'max' sub-elements.
   */
  private function betweenElement(array $output): array {
    $id = 'field_bef_datetime_value';
    $wrapper = $id . '_wrapper';
    if (isset($output[$wrapper][$wrapper][$id]['min'])) {
      return $output[$wrapper][$wrapper][$id];
    }
    if (isset($output[$wrapper][$id]['min'])) {
      return $output[$wrapper][$id];
    }
    $this->fail("Could not find between element for '$id' in the exposed form output.");
  }

  /**
   * Tests isApplicable() returns TRUE for a standard datetime filter.
   */
  public function testIsApplicable(): void {
    $view = $this->initializedViewWithFilter($this->baseFilterConfig());

    $this->assertTrue(
      DatetimePicker::isApplicable($view->filter['field_bef_datetime_value'])
    );
  }

  /**
   * Tests that grouped filters are not applicable.
   */
  public function testIsNotApplicableToGroupedFilters(): void {
    $view = $this->initializedViewWithFilter($this->baseFilterConfig());
    $filter = $view->filter['field_bef_datetime_value'];
    $filter->options['is_grouped'] = TRUE;

    $this->assertFalse(DatetimePicker::isApplicable($filter));
  }

  /**
   * Tests that non-date filters are not applicable.
   */
  public function testIsNotApplicableToNonDateFilters(): void {
    $mock = $this->createMock(FilterPluginBase::class);
    $mock->expects($this->any())->method('isAGroup')->willReturn(FALSE);

    $this->assertFalse(DatetimePicker::isApplicable($mock));
  }

  /**
   * Tests that a filter with a date_handler property is applicable.
   *
   * The date_handler property is set dynamically by contrib modules such as
   * Date API on filter plugins that are not Date subclasses.
   */
  public function testIsApplicableForFilterWithDateHandler(): void {
    $mock = $this->createMock(FilterPluginBase::class);
    $mock->method('isAGroup')->willReturn(FALSE);
    $mock->date_handler = TRUE;

    $this->assertTrue(DatetimePicker::isApplicable($mock));
  }

  /**
   * Tests that the full set of attributes is applied to a converted element.
   *
   * Verifies #type, the HTML type attribute, the CSS class, and autocomplete.
   */
  public function testElementAttributesAreComplete(): void {
    $view = $this->viewWithBef($this->baseFilterConfig(['operator' => '>']));

    $output = $this->getExposedFormRenderArray($view);
    $element = $output['field_bef_datetime_value'];

    $this->assertEquals('date', $element['#type']);
    $this->assertEquals('datetime-local', $element['#attributes']['type']);
    $this->assertContains('bef-datetimepicker', $element['#attributes']['class']);
    $this->assertEquals('off', $element['#attributes']['autocomplete']);
  }

  /**
   * Tests that without a configured default type the element is not prefilled.
   *
   * Default-value conversion must be a no-op when value.type is absent,
   * leaving the element converted to datetime-local but with no #default_value.
   */
  public function testNoDefaultTypeDoesNotSetDefaultValue(): void {
    $view = $this->viewWithBef($this->baseFilterConfig(['operator' => '>']));

    $output = $this->getExposedFormRenderArray($view);
    $element = $output['field_bef_datetime_value'];

    $this->assertEquals('datetime-local', $element['#attributes']['type']);
    $this->assertEmpty($element['#default_value'] ?? NULL);
  }

  /**
   * Tests that an offset value (e.g. 'now') is converted to datetime-local.
   */
  public function testOffsetDefaultValueIsConvertedForDisplay(): void {
    $view = $this->viewWithBef($this->baseFilterConfig([
      'operator' => '>',
      'value' => ['value' => 'now', 'type' => 'offset'],
    ]));

    $output = $this->getExposedFormRenderArray($view);
    $element = $output['field_bef_datetime_value'];

    $this->assertEquals('datetime-local', $element['#attributes']['type']);
    $this->assertMatchesRegularExpression(
      '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',
      $element['#default_value'],
    );
  }

  /**
   * Tests that a fixed date value is normalized to datetime-local format.
   */
  public function testFixedDefaultValueIsNormalized(): void {
    $view = $this->viewWithBef($this->baseFilterConfig([
      'operator' => '>',
      'value' => ['value' => '2025-01-01', 'type' => 'date'],
    ]));

    $output = $this->getExposedFormRenderArray($view);
    $element = $output['field_bef_datetime_value'];

    $this->assertEquals('datetime-local', $element['#attributes']['type']);
    $this->assertEquals('2025-01-01T00:00', $element['#default_value']);
  }

  /**
   * Tests that an empty fixed value leaves #default_value unset.
   */
  public function testEmptyFixedDefaultValueIsIgnored(): void {
    $view = $this->viewWithBef($this->baseFilterConfig([
      'operator' => '>',
      'value' => ['value' => '', 'type' => 'date'],
    ]));

    $output = $this->getExposedFormRenderArray($view);

    $this->assertEmpty($output['field_bef_datetime_value']['#default_value'] ?? NULL);
  }

  /**
   * Tests that between offset values (min/max) are converted to datetime-local.
   */
  public function testBetweenOffsetDefaultValuesAreConvertedForDisplay(): void {
    $view = $this->viewWithBef($this->baseFilterConfig([
      'operator' => 'between',
      'value' => ['min' => 'now', 'max' => '+1 day', 'type' => 'offset'],
    ]));

    $output = $this->getExposedFormRenderArray($view);
    $element = $this->betweenElement($output);

    $this->assertEquals('datetime-local', $element['min']['#attributes']['type']);
    $this->assertEquals('datetime-local', $element['max']['#attributes']['type']);

    $this->assertMatchesRegularExpression(
      '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',
      $element['min']['#default_value'],
    );
    $this->assertMatchesRegularExpression(
      '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',
      $element['max']['#default_value'],
    );
  }

  /**
   * Tests that fixed between values (min/max) are normalized to datetime-local.
   */
  public function testBetweenFixedDefaultValuesAreNormalized(): void {
    $view = $this->viewWithBef($this->baseFilterConfig([
      'operator' => 'between',
      'value' => ['min' => '2025-01-01', 'max' => '2025-12-31', 'type' => 'date'],
    ]));

    $output = $this->getExposedFormRenderArray($view);
    $element = $this->betweenElement($output);

    $this->assertEquals('datetime-local', $element['min']['#attributes']['type']);
    $this->assertEquals('datetime-local', $element['max']['#attributes']['type']);

    $this->assertEquals('2025-01-01T00:00', $element['min']['#default_value']);
    $this->assertEquals('2025-12-31T00:00', $element['max']['#default_value']);
  }

  /**
   * Tests that with an exposed operator the single-value input is converted.
   *
   * When use_operator is TRUE, NumericFilter::valueForm() produces a container
   * at $form[$field_id] with 'value', 'min', and 'max' sub-inputs. The widget
   * must convert the 'value' sub-input, not just min/max.
   */
  public function testExposedOperatorConvertsValueInput(): void {
    $view = $this->viewWithBef($this->baseFilterConfig([
      'operator' => '>',
      'expose' => [
        'identifier' => 'field_bef_datetime_value',
        'use_operator' => TRUE,
        'operator_id' => 'field_bef_datetime_value_op',
      ],
      'value' => ['value' => '2025-06-01', 'type' => 'date'],
    ]));

    $output = $this->getExposedFormRenderArray($view);
    // With use_operator, Views wraps the element the same way as between:
    // [$wrapper_id][$wrapper_id][$field_id] with value/min/max sub-inputs.
    $element = $this->betweenElement($output);

    $this->assertEquals('datetime-local', $element['value']['#attributes']['type']);
    $this->assertEquals('2025-06-01T00:00', $element['value']['#default_value']);
  }

}
