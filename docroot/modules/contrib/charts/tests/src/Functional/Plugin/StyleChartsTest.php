<?php

namespace Drupal\Tests\charts\Functional\Plugin;

use Drupal\Tests\views\Functional\ViewTestBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the chart style views plugin.
 *
 * @group charts
 */
class StyleChartsTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_charts'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['charts', 'charts_test', 'views'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['charts_test']): void {
    parent::setUp($import_test_views, $modules);

    $this->enableViewsTestModule();
  }

  /**
   * Tests the generated JSON generated from the views.
   */
  public function testGeneratedJson() {
    $this->drupalGet('test-charts');

    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    $attribute = 'data-chart';
    $chart_container = $this->assertSession()->elementAttributeExists('css', '#chart-test-charts-page-1', $attribute);

    $actual = str_replace('&quot;', '"', (string) $chart_container->getAttribute($attribute));
    $expected = '{"title":{"text":"Test charts","color":"#000","position":"out","font":{"weight":"normal","style":"normal","size":14}},"subtitle":{"text":""},"type":"column","colors":["#006fb0","#f07c33","#342e9c","#579b17","#3f067a","#cbde67","#7643b6","#738d00","#c157c7","#02dab1","#ed56b4","#d8d981","#004695","#736000","#a5a5ff","#833a00","#ff9ee9","#684507","#fe4f85","#5d0011","#ffa67b","#88005c","#ff9b8f","#85000f","#ff7581"],"tooltips":true,"foo_configuration":"","series":[]}';
    $this->assertEquals($expected, $actual);
  }

}
