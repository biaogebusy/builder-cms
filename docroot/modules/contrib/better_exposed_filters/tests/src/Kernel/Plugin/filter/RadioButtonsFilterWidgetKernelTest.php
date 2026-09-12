<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\RadioButtons;
use Drupal\Core\Form\FormState;
use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the RadioButtons/Checkboxes filter widget.
 *
 * @group better_exposed_filters
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\RadioButtons
 */
class RadioButtonsFilterWidgetKernelTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * Tests that the RadioButtons widget applies to option-based filters.
   */
  public function testIsApplicableToOptionFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    $this->assertTrue(
      RadioButtons::isApplicable($view->filter['field_bef_integer_value']),
    );
  }

  /**
   * Tests that the RadioButtons widget applies to boolean filters.
   */
  public function testIsApplicableToBooleanFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    $this->assertTrue(
      RadioButtons::isApplicable($view->filter['field_bef_boolean_value']),
    );
  }

  /**
   * Tests that the RadioButtons widget applies to taxonomy select filters.
   */
  public function testIsApplicableToTaxonomyFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    $this->assertTrue(
      RadioButtons::isApplicable(
        $view->filter['term_node_tid_depth'],
        $view->filter['term_node_tid_depth']->options,
      ),
    );
  }

  /**
   * Tests that the RadioButtons widget does not apply to numeric filters.
   */
  public function testIsNotApplicableToNumericFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    $this->assertFalse(
      RadioButtons::isApplicable($view->filter['field_bef_price_value']),
    );
  }

  /**
   * Tests the exposed checkboxes filter widget.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testExposedCheckboxes(): void {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');

    $display['display_options']['filters']['field_bef_integer_value']['expose']['multiple'] = TRUE;
    $display['display_options']['filters']['term_node_tid_depth']['expose']['multiple'] = TRUE;
    $display['display_options']['filters']['term_node_tid_depth']['hierarchy'] = TRUE;

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_integer_value' => [
          'plugin_id' => 'bef',
        ],
        'term_node_tid_depth' => [
          'plugin_id' => 'bef',
        ],
      ],
    ]);

    $this->renderExposedForm($view);

    // Check "FIELD_BEF_INTEGER" filter is rendered as checkboxes.
    $actual = $this->xpath('//form//input[@type="checkbox" and starts-with(@name, "field_bef_integer_value")]');
    $this->assertCount(6, $actual);

    // Check "TERM_NODE_TID_DEPTH" filter is rendered as nested checkboxes.
    $actual = $this->xpath("//form//div[contains(concat(' ',normalize-space(@class),' '),' bef-nested ')]");
    $this->assertCount(1, $actual);

    $actual = $this->xpath('//form//div[@id="edit-term-node-tid-depth--2"]/div/ul/li/div/input[@type="checkbox" and starts-with(@name, "term_node_tid_depth")]');
    $this->assertCount(3, $actual);

    $actual = $this->xpath('//form//div[@id="edit-term-node-tid-depth--2"]/div/ul/li/ul/li/div/input[@type="checkbox" and starts-with(@name, "term_node_tid_depth")]');
    $this->assertCount(5, $actual);

    $actual = $this->xpath('//form//div[@id="edit-term-node-tid-depth--2"]/div/ul/li/ul/li/ul/li/div/input[@type="checkbox" and starts-with(@name, "term_node_tid_depth")]');
    $this->assertCount(14, $actual);

    $view->destroy();
  }

  /**
   * Tests the exposed radio buttons filter widget.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testExposedRadioButtons(): void {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');

    $display['display_options']['filters']['term_node_tid_depth']['hierarchy'] = TRUE;

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_boolean_value' => [
          'plugin_id' => 'bef',
        ],
        'term_node_tid_depth' => [
          'plugin_id' => 'bef',
        ],
      ],
    ]);

    $this->renderExposedForm($view);

    // Check filter is rendered as radio buttons (Any, true, false).
    $actual = $this->xpath('//form//input[@type="radio" and @name="field_bef_boolean_value"]');
    $this->assertCount(3, $actual);

    // Check "TERM_NODE_TID_DEPTH" filter is rendered as nested radio buttons.
    $actual = $this->xpath("//form//div[contains(concat(' ',normalize-space(@class),' '),' bef-nested ')]");
    $this->assertCount(1, $actual);

    $actual = $this->xpath('//form//div[@id="edit-term-node-tid-depth--2"]/div/ul/li/div/input[@type="radio" and starts-with(@name, "term_node_tid_depth")]');
    $this->assertCount(4, $actual);

    $actual = $this->xpath('//form//div[@id="edit-term-node-tid-depth--2"]/div/ul/li/ul/li/div/input[@type="radio" and starts-with(@name, "term_node_tid_depth")]');
    $this->assertCount(5, $actual);

    $actual = $this->xpath('//form//div[@id="edit-term-node-tid-depth--2"]/div/ul/li/ul/li/ul/li/div/input[@type="radio" and starts-with(@name, "term_node_tid_depth")]');
    $this->assertCount(14, $actual);

    $view->destroy();
  }

  /**
   * User-input filtering keeps checked options and drops empty/null/0 items.
   */
  public function testExposedFormAlterFiltersUserInput(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    /** @var \Drupal\better_exposed_filters\Plugin\BetterExposedFiltersWidgetInterface $widget */
    $widget = \Drupal::service('plugin.manager.better_exposed_filters_filter_widget')
      ->createInstance('bef');
    $widget->setViewsHandler($view->filter['field_bef_integer_value']);

    $form_state = new FormState();
    $form_state->setUserInput([
      'field_bef_integer_value' => [
        1 => 1,
        2 => 0,
        3 => 3,
        4 => '',
        5 => NULL,
        6 => '0',
      ],
      'unrelated_scalar' => 'kept',
      'unrelated_null' => NULL,
    ]);
    $form = [];
    $widget->exposedFormAlter($form, $form_state);

    $input = $form_state->getUserInput();

    $this->assertSame([1 => 1, 3 => 3, 6 => '0'], $input['field_bef_integer_value']);

    // Scalar non-array entries are untouched (nulls dropped).
    $this->assertSame('kept', $input['unrelated_scalar']);
    $this->assertArrayNotHasKey('unrelated_null', $input);

    $view->destroy();
  }

}
