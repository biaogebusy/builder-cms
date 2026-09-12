<?php

namespace Drupal\Tests\webform_views\Kernel\sort;

use Drupal\Tests\webform_views\Kernel\WebformViewsTestBase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Reasonable starting point for testing webform views sort handlers.
 */
abstract class WebformViewsSortTestBase extends WebformViewsTestBase {

  /**
   * Test sorting handler.
   *
   * @param string $order
   *   Direction of sorting. Allowed values are:
   *   - ASC: to sort ascending.
   *   - DESC: to sort descending.
   * @param array $expected
   *   Expected output from $this->renderView() with the specified above
   *   sorting.
   */
  #[DataProvider('providerSort')]
  public function testSort(string $order, array $expected): void {
    $this->webform = $this->createWebform(static::$webform_elements);
    $this->createWebformSubmissions(static::$webform_submissions_data, $this->webform);

    $view_handlers = static::$view_handlers;
    $view_handlers['sort'][0]['options']['order'] = $order;

    $this->view = $this->initView($this->webform, $view_handlers);

    $rendered_cells = $this->renderView($this->view);

    $this->assertSame($expected, $rendered_cells, 'Sorting works for ' . $order . ' order');
  }

  /**
   * Data provider for the ::testSort() method.
   *
   * You might want to override this method with more specific cases in a child
   * class.
   */
  public static function providerSort(): array {
    $tests = [];

    $tests[] = [
      'ASC',
      static::$webform_submissions_data,
    ];

    $tests[] = [
      'DESC',
      array_reverse(static::$webform_submissions_data),
    ];

    return $tests;
  }

}
