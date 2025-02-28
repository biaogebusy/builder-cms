/**
 * @file
 * Applies textures to Highcharts charts.
 */
(function (Drupal, once) {

  'use strict';
  Drupal.charts_highcharts = Drupal.charts_highcharts || {};

  Drupal.behaviors.chartsHighchartsAddTexture = {
    attach: function (context) {
      once('charts-highchart-texture', '.charts-highchart', context).forEach(function (element) {
        element.addEventListener('drupalChartsConfigsInitialization', function (e) {
          let data = e.detail;
          const id = data.drupalChartDivId;
          // Add textures to series.
          if ('series' in data && data.series[0].color !== undefined && typeof data.series[0].data[0] === "number") {
            for (let i = 0; i < data.series.length; i++) {
              data.series[i].color = Drupal.charts_highcharts.getPattern(Drupal.charts_highcharts.getUnderTenIndex(i), data.series[i].color);
            }
          } else if ('series' in data && typeof data.series[0].data[0] === "object" && data.series[0].data[0].color !== undefined) {
            for (let i = 0; i < data.series[0].data.length; i++) {
              data.series[0].data[i].color = Drupal.charts_highcharts.getPattern(Drupal.charts_highcharts.getUnderTenIndex(i), data.series[0].data[i].color);
            }
          }
          if ('series' in data && typeof data.series[0].data[0] === "object" && data.series[0].data[0].color === undefined) {
            for (let i = 0; i < data.colors.length; i++) {
              data.colors[i] = Drupal.charts_highcharts.getPattern(Drupal.charts_highcharts.getUnderTenIndex(i), data.colors[i]);
            }
          }

          Drupal.Charts.Contents.update(id, data);
        });
      });
    }
  };

  /**
   * Get under ten index in case there are more than ten series because
   * Highcharts patterns array has ten patterns
   * 0 - 9 index.
   */
  Drupal.charts_highcharts.getUnderTenIndex = function (k) {
    if (k < 10) {
      return k;
    } else {
      while (k >= 10) {
        // Sum the digits of k.
        k = k.toString().split('').reduce(function (a, b) {
          return parseInt(a) + parseInt(b);
        });
      }
      return k;
    }
  }

  /**
   * Get a default pattern, but using the series color.
   * The index-argument refers to which default pattern to use
   */
  Drupal.charts_highcharts.getPattern = function (index, color) {
    return {
      pattern: Highcharts.merge(Highcharts.patterns[index], {
        color: color
      })
    };
  }

}(Drupal, once));
