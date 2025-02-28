<?php

namespace Drupal\Tests\charts\Kernel;

use Drupal\Tests\charts\Traits\ConfigUpdateTrait;

/**
 * Tests the chart type element.
 *
 * @group charts
 */
class ChartElementTest extends ChartsKernelTestBase {

  use ConfigUpdateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'charts',
    'charts_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->updateFooConfiguration('bar');
  }

  /**
   * Tests the chart test element.
   */
  public function testChartElement() {
    $element = [
      '#type' => 'chart',
      '#library' => 'charts_test_library',
      '#chart_type' => 'column',
    ];

    $json = "{'title':{'text':null,'color':'#000','position':'out','font':{'weight':'normal','style':'normal','size':14}},'subtitle':{'text':null},'type':'column','colors':['#2f7ed8','#0d233a','#8bbc21','#910000','#1aadce','#492970','#f28f43','#77a1e5','#c42525','#a6c96a'],'tooltips':true,'foo_configuration':'bar','series':[]}";
    $this->assertElementJson($element, $json);

    // Testing raw options.
    $element['#raw_options'] = ['title' => ['text' => 'Foo']];
    $path = ['title', 'text'];
    $this->assertJsonPropertyHasValue($element, $path, 'Foo');
    $this->assertJsonPropertyHasValue($element, ['type'], 'column');
  }

}
