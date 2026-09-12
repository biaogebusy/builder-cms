<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\Hidden;
use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the Hidden filter widget.
 *
 * @group better_exposed_filters
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\Hidden
 */
class HiddenFilterWidgetKernelTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * Tests that the Hidden widget applies to standard option filters.
   */
  public function testIsApplicableToOptionFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    // Integer filter with 'in' operator should be applicable via parent.
    $this->assertTrue(
      Hidden::isApplicable($view->filter['field_bef_integer_value']),
    );
  }

  /**
   * Tests that the Hidden widget applies to date filters.
   */
  public function testIsApplicableToDateFilter(): void {
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

    $this->assertTrue(
      Hidden::isApplicable($view->filter['created']),
    );
  }

  /**
   * Tests hiding element with single option.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSingleExposedHiddenElement(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_boolean_value' => [
          'plugin_id' => 'bef_hidden',
        ],
      ],
    ]);

    $this->renderExposedForm($view);

    $actual = $this->xpath('//form//input[@type="hidden" and starts-with(@name, "field_bef_boolean_value")]');
    $this->assertCount(1, $actual);

    $view->destroy();
  }

  /**
   * Tests hiding element with multiple options.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMultipleExposedHiddenElement(): void {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');

    $display['display_options']['filters']['field_bef_integer_value']['expose']['multiple'] = TRUE;

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_integer_value' => [
          'plugin_id' => 'bef_hidden',
        ],
      ],
    ]);

    $this->renderExposedForm($view);

    $actual = $this->xpath('//form//label[@type="label" and starts-with(@for, "edit-field-bef-integer-value")]');
    $this->assertCount(0, $actual);

    $actual = $this->xpath('//form//input[@type="hidden" and starts-with(@name, "field_bef_integer_value")]');
    $this->assertCount(0, $actual);
  }

}
