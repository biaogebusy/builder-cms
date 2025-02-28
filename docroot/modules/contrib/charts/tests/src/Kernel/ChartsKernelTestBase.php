<?php

namespace Drupal\Tests\charts\Kernel;

use Drupal\Component\Utility\NestedArray;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Base class for chart kernel tests.
 */
abstract class ChartsKernelTestBase extends KernelTestBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Asserts that the given element returned the expected json.
   *
   * @param array $element
   *   The element to check.
   * @param string $expected_json
   *   The expected json.
   */
  public function assertElementJson(array $element, string $expected_json) {
    $this->assertEquals($expected_json, $this->getDataChartJson($element));
  }

  /**
   * Asserts that json property has value by given element.
   *
   * @param array $element
   *   The element structure.
   * @param array $parents
   *   The parent structure where the value is.
   * @param string $expected_value
   *   The expected value.
   */
  public function assertJsonPropertyHasValue(array $element, array $parents, string $expected_value) {
    $json = $this->getDataChartJson($element);
    $decoded_json = json_decode(str_replace("'", '"', $json), TRUE);
    $actual_value = NestedArray::getValue($decoded_json, $parents);
    $this->assertEquals($expected_value, $actual_value);
  }

  /**
   * Get the json data.
   *
   * @param array $element
   *   The element to be rendered.
   *
   * @return string
   *   The string with the data from the chart.
   */
  private function getDataChartJson(array $element): string {
    $renderer_output = $this->renderer->renderRoot($element);
    $crawler = new Crawler();
    $crawler->addHtmlContent(str_replace('&quot;', "'", $renderer_output));
    return $crawler->filter('[data-chart]')->attr('data-chart');
  }

  /**
   * Get the chart style from the element.
   *
   * @param array $element
   *   The element to be tested.
   * @param string $filter_selector
   *   The selector to use for crawler filter.
   *
   * @return array
   *   The styles as an array.
   */
  protected function getChartStyle(array $element, string $filter_selector = '[data-chart]'): array {
    $renderer_output = $this->renderer->renderRoot($element);
    $crawler = new Crawler();
    $crawler->addHtmlContent(str_replace('&quot;', "'", $renderer_output));
    if (!$crawler->filter($filter_selector)->count()) {
      return [];
    }

    $style_attribute = $crawler->filter($filter_selector)->attr('style');
    // Explode the style attribute, filtering out the last (empty) element.
    $styles = $style_attribute ? explode(';', rtrim($style_attribute, ';')) : [];

    // Convert the string into an array of key/value pairs.
    $parsed_styles = [];
    foreach ($styles as $style) {
      if (strlen(trim($style)) > 0) {
        [$property, $value] = explode(':', trim($style));
        $parsed_styles[$property] = $value;
      }
    }

    return $parsed_styles;
  }

}
