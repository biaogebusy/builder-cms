<?php

namespace Drupal\Tests\range\Functional\Views;

use Drupal\views\Views;

/**
 * Tests the range views argument handler.
 *
 * @group range
 */
class RangeViewsArgumentTest extends RangeViewsTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_argument_range'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['range_test']): void {
    parent::setUp($import_test_views, $modules);

    // Add some basic test nodes.
    $ranges = [
      ['from' => 0, 'to' => 10],
      ['from' => 5, 'to' => 9],
      ['from' => -5, 'to' => 15],
      ['from' => -10, 'to' => 5],
    ];
    foreach ($ranges as $range) {
      $this->nodes[] = $this->drupalCreateNode([
        'type' => $this->bundle,
        $this->fieldName => $range,
      ]);
    }
  }

  /**
   * Tests range views filter.
   */
  public function testRangeViewsArgument() {
    $view = Views::getView('test_argument_range');

    // Range contains, exclude endpoints.
    $view->initHandlers();
    $view->argument[$this->fieldName]->options['operator'] = 'within';
    $view->argument[$this->fieldName]->options['include_endpoints'] = FALSE;
    $view->setDisplay('default');
    $this->executeView($view, [5]);
    $expected_result = [
      ['nid' => $this->nodes[0]->id()],
      ['nid' => $this->nodes[2]->id()],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $this->map, 'Range views argument (contains, exclude endpoints) produces correct results');
    $view->destroy();

    // Range contains, include endpoints.
    $view->initHandlers();
    $view->argument[$this->fieldName]->options['operator'] = 'within';
    $view->argument[$this->fieldName]->options['include_endpoints'] = TRUE;
    $view->setDisplay('default');
    $this->executeView($view, [5]);
    $expected_result = [
      ['nid' => $this->nodes[0]->id()],
      ['nid' => $this->nodes[1]->id()],
      ['nid' => $this->nodes[2]->id()],
      ['nid' => $this->nodes[3]->id()],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $this->map, 'Range views argument (contains, include endpoints) produces correct results');
    $view->destroy();

    // Range does contains, exclude endpoints.
    $view->initHandlers();
    $view->argument[$this->fieldName]->options['operator'] = 'not within';
    $view->argument[$this->fieldName]->options['include_endpoints'] = FALSE;
    $view->setDisplay('default');
    $this->executeView($view, [5]);
    $expected_result = [
      ['nid' => $this->nodes[1]->id()],
      ['nid' => $this->nodes[3]->id()],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $this->map, 'Range views argument (does not contain, exclude endpoints) produces correct results');
    $view->destroy();

    // Range does contains, include endpoints.
    $view->initHandlers();
    $view->argument[$this->fieldName]->options['operator'] = 'not within';
    $view->argument[$this->fieldName]->options['include_endpoints'] = TRUE;
    $view->setDisplay('default');
    $this->executeView($view, [5]);
    $expected_result = [];
    $this->assertIdenticalResultset($view, $expected_result, $this->map, 'Range views argument (does not contain, include endpoints) produces correct results');
    $view->destroy();
  }

}
