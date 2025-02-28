<?php

/**
 * @file
 * Documentation on hooks provided by the Charts module.
 *
 * Charts module provides 4 element types that can be used to construct a chart.
 * In its most basic form, a chart may be created by specifying an element
 * with the #type property "chart". However, for enough information to
 * accurately data to an audience, you need a little more. Below is a very
 * basic example of a column chart.
 *
 * @code
 * $chart = [
 *   '#type' => 'chart',
 *   '#chart_type' => 'column',
 *   'series' => [
 *     '#type' => 'chart_data',
 *     '#title' => t('Responses'),
 *     '#data' => [60, 40],
 *   ],
 *   'xaxis' => [
 *     '#type' => 'chart_xaxis',
 *     '#labels' => [t('Yes'), t('No')],
 *   ]
 * ];
 * @endcode
 *
 * On charts that have multiple axes, you'll need to add individual sub-elements
 * for each series of data. If you desire, you may also customize the axes by
 * providing an axis element too.
 *
 * @code
 * $chart = [
 *   '#type' => 'chart',
 *   '#chart_type' => 'column',
 * ];
 * $chart['series'] = [
 *   '#type' => 'chart_data',
 *   '#title' => t('Responses'),
 *   '#data' => [10, 20, 30],
 * ];
 * $chart['xaxis'] = [
 *   '#type' => 'chart_xaxis',
 *   '#title' => t('Month'),
 *   '#labels' => [t('Jan'), t('Feb'), t('Mar')],
 * ];
 * @endcode
 *
 * Once you have generated a chart object, you can run
 * \Drupal::service('renderer')->render() on it to turn it into HTML:
 *
 * @code
 * $output = \Drupal::service('renderer')->render($chart);
 * @endcode
 *
 * There are many, many properties available for the four chart types (chart,
 * chart_data, chart_xaxis, and chart_yaxis). For a full list, see the
 * charts_element_info() function.
 *
 * This module also includes a number of examples for reference in the
 * charts_api_example module, and displayed at /charts/example/display.
 *
 * @see Drupal\charts\Element folder
 */

/**
 * Alter an individual chart before it is printed.
 *
 * @param array $element
 *   The chart renderable. Passed in by reference.
 * @param string $chart_id
 *   The chart identifier, pulled from the $chart['#chart_id'] property
 *   (if any). Not all charts have a chart identifier.
 */
function hook_chart_alter(array &$element, $chart_id) {
  if ($chart_id === 'view_name__display_name') {
    // Individual properties may be modified.
    $element['#title_font_size'] = 20;
  }
}

/**
 * Alter an individual chart before it's rendered.
 *
 * Same as hook_chart_alter(), only including the $chart_id in the function
 * name instead of being passed in as an argument.
 *
 * @see hook_chart_alter()
 */
function hook_chart_CHART_ID_alter(array &$element) {
}

/**
 * Alter an individual chart's raw library representation.
 *
 * This hook is called AFTER hook_chart_alter(), after Charts module has
 * converted the renderable into the chart definition that will be used by the
 * library. Note that the structure of $definition will differ based on the
 * charting library used. Switching charting libraries may cause your code
 * to break when using this hook.
 *
 * Even though this hook may be fragile, it may provide developers with access
 * to library-specific functionality.
 *
 * @param array $definition
 *   The chart definition to be modified. The raw values are passed directly to
 *   the charting library.
 * @param array $element
 *   The chart renderable. This may be used for reference (or read to add
 *   support for new properties), but any changes to this variable will not
 *   have an effect on output.
 * @param string $chart_id
 *   The chart ID, derived from the $chart['#chart_id'] property. Note that not
 *   all charts may have a $chart_id.
 */
function hook_chart_definition_alter(array &$definition, array $element, $chart_id) {
  if ($element['#chart_library'] === 'google') {
    $definition['options']['titleTextStyle']['fontSize'] = 20;
  }
  elseif ($element['#chart_library'] === 'highcharts') {
    $definition['title']['style']['fontSize'] = 20;
  }
}

/**
 * Alter an individual chart before it's rendered.
 *
 * Same as hook_chart_definition_alter(), only including the $chart_id in the
 * function name instead of being passed in as an argument.
 *
 * @see hook_chart_definition_alter()
 */
function hook_chart_definition_CHART_ID_alter(array &$definition, array $element, $chart_id) {
}

/**
 * Alter the list of supported chart types for a chart plugin.
 *
 * To use this hook, first define the chart types you want to add in a file
 * named <module>.charts_types.yml to your module's root directory. For example,
 * if your module is named "charts_foo", the file should be named
 * "charts_foo.charts_types.yml".
 *
 * The contents of this file will look something like:
 *
 * @code
 * candlestick:
 *   label: 'Candlestick'
 *   axis: 'xy'
 *   axis_inverted: false
 *   stacking: false
 * @endcode
 *
 * @see charts.charts_types.yml
 *
 * With that file in place, you can now implement this hook. See below for an
 * example adding the 'candlestick' chart type to the 'highcharts' chart plugin.
 *
 * @param array $types
 *   An array of supported chart types.
 * @param string $chart_plugin_id
 *   The chart plugin ID.
 */
function hook_charts_plugin_supported_chart_types_alter(array &$types, string $chart_plugin_id) {
  if ($chart_plugin_id === 'highcharts') {
    $types[] = 'candlestick';
  }
}
