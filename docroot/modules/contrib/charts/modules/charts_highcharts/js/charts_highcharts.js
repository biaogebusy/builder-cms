/**
 * @file
 * JavaScript's integration between Highcharts and Drupal.
 */
(function (Drupal, once, drupalSettings) {

  'use strict';

  Drupal.behaviors.chartsHighcharts = {
    attach: function (context) {
      const contents = new Drupal.Charts.Contents();
      if (drupalSettings.hasOwnProperty('charts') && drupalSettings.charts.highcharts.global_options) {
        Highcharts.setOptions(drupalSettings.charts.highcharts.global_options);
      }
      once('charts-highchart', '.charts-highchart', context).forEach(function (element) {
        const id = element.id;
        let config = contents.getData(id);
        if (!config) {
          return;
        }

        config.chart.renderTo = id;
        new Highcharts.Chart(config);
        if (element.nextElementSibling && element.nextElementSibling.hasAttribute('data-charts-debug-container')) {
          element.nextElementSibling.querySelector('code').innerText = JSON.stringify(config, null, ' ');
        }
      });
    },
    detach: function (context, settings, trigger) {
      if (trigger === 'unload') {
        once('charts-highchart-detach', '.charts-highchart', context).forEach(function (element) {
          if (!element.dataset.hasOwnProperty('highchartsChart')) {
            return;
          }
          Highcharts.charts[element.dataset.highchartsChart].destroy();
        });
      }
    }
  };
}(Drupal, once, drupalSettings));
