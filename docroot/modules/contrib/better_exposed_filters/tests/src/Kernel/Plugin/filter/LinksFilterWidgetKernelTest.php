<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\Links;
use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the Links filter widget.
 *
 * @group better_exposed_filters
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\Links
 */
class LinksFilterWidgetKernelTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * Tests that the Links widget applies to option-based filters.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testIsApplicableToOptionFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    $this->assertTrue(
      Links::isApplicable($view->filter['field_bef_integer_value']),
    );
  }

  /**
   * Tests that the Links widget does not apply to numeric filters.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testIsNotApplicableToNumericFilter(): void {
    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();

    $this->assertFalse(
      Links::isApplicable($view->filter['field_bef_price_value']),
    );
  }

  /**
   * Tests the exposed links filter widget.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testExposedLinks(): void {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');

    $display['display_options']['filters']['term_node_tid_depth']['hierarchy'] = TRUE;

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_integer_value' => [
          'plugin_id' => 'bef_links',
        ],
        'term_node_tid_depth' => [
          'plugin_id' => 'bef_links',
        ],
      ],
    ]);

    $this->renderExposedForm($view);

    // Check "FIELD_BEF_INTEGER" filter is rendered as links.
    $actual = $this->xpath('//form//div[contains(@id, "field-bef-integer-value")]//a[@data-bef-value]');
    $this->assertCount(7, $actual);

    // Check "TERM_NODE_TID_DEPTH" filter is rendered as nested links.
    $actual = $this->xpath("//form//div[contains(concat(' ',normalize-space(@class),' '),' bef-nested ')]");
    $this->assertCount(1, $actual);

    $actual = $this->xpath('//form//div[@id="edit-term-node-tid-depth--2"]/ul/li/a[@data-bef-value]');
    $this->assertCount(4, $actual);

    $actual = $this->xpath('//form//div[@id="edit-term-node-tid-depth--2"]/ul/li/ul/li/a[@data-bef-value]');
    $this->assertCount(5, $actual);

    $actual = $this->xpath('//form//div[@id="edit-term-node-tid-depth--2"]/ul/li/ul/li/ul/li/a[@data-bef-value]');
    $this->assertCount(14, $actual);

    $view->destroy();
  }

}
