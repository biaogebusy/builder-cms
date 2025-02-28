<?php

namespace Drupal\Tests\webform_views\Kernel\field;

use Drupal\Tests\webform_views\Kernel\WebformViewsTestBase;

/**
 * Reasonable starting point for testing webform views field handlers.
 */
abstract class WebformViewsFieldTestBase extends WebformViewsTestBase {

  /**
   * Test field rendering.
   *
   * Execute a view and make sure the field handler we are testing produced
   * correct HTML markup.
   */
  public function testField() {
    $this->webform = $this->createWebform($this->webform_elements);
    $this->createWebformSubmissions($this->webform_submissions_data, $this->webform);
    $this->view = $this->initView($this->webform, $this->view_handlers);

    $rendered_cells = $this->renderView($this->view);

    $this->assertSame($this->webform_submissions_data, $rendered_cells, 'Views field on a webform element produces correct output.');
  }

  /**
   * Test the multivalue element placing all values into single cell.
   */
  public function testMultiValueAllInOne() {
    // Convert each webform element into multivalue before creating the webform.
    $webform_elements = $this->webform_elements;
    foreach ($webform_elements as $k => $v) {
      $webform_elements[$k]['#multiple'] = 10;
    }
    $this->webform = $this->createWebform($webform_elements);

    $this->createWebformSubmissions($this->webform_submission_multivalue_data, $this->webform);

    // Convert each view field handler into 'all in one' multi value before
    // creating the view.
    $view_handlers = $this->view_handlers;
    foreach ($view_handlers['field'] as $k => $v) {
      $view_handlers['field'][$k]['options']['webform_multiple_value'] = TRUE;
    }
    $this->view = $this->initView($this->webform, $view_handlers);

    $rendered_cells = $this->renderView($this->view);

    $expected = [];
    foreach ($this->webform_submission_multivalue_data as $i => $submission) {
      foreach ($submission as $element => $data) {
        $render = [
          '#theme' => 'item_list',
          '#items' => $data,
        ];
        $expected[$i][$element] = (string) \Drupal::service('renderer')->renderRoot($render);
      }
    }

    $this->assertSame($expected, $rendered_cells);
  }

  /**
   * Test the multivalue element placing single value into a cell.
   */
  public function testMultiValueDeltaOffset() {
    // Delta offset within element multivalues to display in the cell.
    $offset = 0;

    // Convert each webform element into multivalue before creating the webform.
    $webform_elements = $this->webform_elements;
    foreach ($webform_elements as $k => $v) {
      $webform_elements[$k]['#multiple'] = 10;
    }
    $this->webform = $this->createWebform($webform_elements);

    $this->createWebformSubmissions($this->webform_submission_multivalue_data, $this->webform);

    // Convert each view field handler into 'all in one' multi value before
    // creating the view.
    $view_handlers = $this->view_handlers;
    foreach ($view_handlers['field'] as $k => $v) {
      $view_handlers['field'][$k]['options']['webform_multiple_value'] = FALSE;
      $view_handlers['field'][$k]['options']['webform_multiple_delta'] = $offset;
    }

    $this->view = $this->initView($this->webform, $view_handlers);

    $rendered_cells = $this->renderView($this->view);

    $expected = [];
    foreach ($this->webform_submission_multivalue_data as $i => $submission) {
      foreach ($submission as $element => $data) {
        $expected[$i][$element] = $data[$offset];
      }
    }

    $this->assertSame($expected, $rendered_cells);
  }

  /**
   * Test click sorting functionality.
   *
   * @param string $field_handler_id
   *   Field handler ID on which to initialize click sorting.
   * @param string $order
   *   Order of the click sorting. Either 'asc' or 'desc'.
   * @param array $expected
   *   Expected output from $this->renderView() with the specified above click
   *   sorting.
   *
   * @dataProvider providerClickSort()
   */
  public function testClickSort($field_handler_id, $order, $expected) {
    $this->webform = $this->createWebform($this->webform_elements);
    $this->createWebformSubmissions($this->webform_submissions_data, $this->webform);
    $this->view = $this->initView($this->webform, $this->view_handlers);

    $this->view->getExecutable()->build();
    $this->view->getExecutable()->field[$field_handler_id]->clickSort($order);
    $this->view->getExecutable()->built = FALSE;

    $rendered_cells = $this->renderView($this->view);

    $this->assertSame($expected, $rendered_cells, 'Click sorting works for ' . $order . ' order');
  }

  /**
   * Data provider for the ::testClickSort() method.
   *
   * You might want to override this method with more specific cases in a child
   * class.
   */
  public function providerClickSort() {
    $tests = [];

    $tests[] = [
      $this->view_handlers['field'][0]['id'],
      'asc',
      $this->webform_submissions_data,
    ];

    $tests[] = [
      $this->view_handlers['field'][0]['id'],
      'desc',
      array_reverse($this->webform_submissions_data),
    ];

    return $tests;
  }

}
