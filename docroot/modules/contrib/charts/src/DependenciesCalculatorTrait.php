<?php

namespace Drupal\charts;

/**
 * Provide method to calculate dependencies for charts config.
 */
trait DependenciesCalculatorTrait {

  /**
   * The chart library plugin manager.
   *
   * @var \Drupal\charts\ChartManager
   */
  protected $chartPluginManager;

  /**
   * The chart type plugin library manager.
   *
   * @var \Drupal\charts\TypeManager
   */
  protected $chartTypePluginManager;

  /**
   * Calculates the dependencies given the chart library and chart type.
   *
   * @param string $chart_library_id
   *   The chart library plugin id.
   * @param string $chart_type_id
   *   The chart type plugin id.
   *
   * @return array
   *   The calculated dependencies.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function calculateDependencies(string $chart_library_id, string $chart_type_id):array {
    $calculated_dependencies = [];
    $dependent_modules = [];
    if ($chart_library_id) {
      // Getting charting library provider and set it as one of the
      // dependencies for the chart settings.
      $plugin_definition = $this->chartPluginManager()->getDefinition($chart_library_id);
      $dependent_modules = [$plugin_definition['provider']];
    }
    if ($chart_type_id) {
      // Do the same thing for the chart type unless it was added by the main
      // "charts" module.
      $plugin_definition = $this->chartTypePluginManager()->getDefinition($chart_type_id);
      $provider = $plugin_definition['provider'];
      if ($provider !== 'charts' && !in_array($provider, $dependent_modules)) {
        $dependent_modules[] = $provider;
      }
    }
    if ($dependent_modules) {
      $calculated_dependencies = ['module' => $dependent_modules];
    }
    return $calculated_dependencies;
  }

  /**
   * Initialize the chart plugin manager if needed.
   *
   * @return \Drupal\charts\ChartManager
   *   The chart manager plugin.
   */
  private function chartPluginManager(): ChartManager {
    if (!isset($this->chartPluginManager)) {
      $this->chartPluginManager = \Drupal::service('plugin.manager.charts');
    }
    return $this->chartPluginManager;
  }

  /**
   * Initialize the chart type plugin manager if needed.
   *
   * @return \Drupal\charts\TypeManager
   *   The chart type manager plugin.
   */
  private function chartTypePluginManager(): TypeManager {
    if (!isset($this->chartTypePluginManager)) {
      $this->chartTypePluginManager = \Drupal::service('plugin.manager.charts_type');
    }
    return $this->chartTypePluginManager;
  }

}
