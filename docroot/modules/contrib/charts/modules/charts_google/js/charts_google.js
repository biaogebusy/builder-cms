/**
 * @file
 * JavaScript integration between Google and Drupal.
 */
(function (Drupal, drupalSettings, once) {

  'use strict';

  Drupal.googleCharts = Drupal.googleCharts || {charts: []};

  /**
   * Behavior to initialize Google Charts.
   *
   * @type {{attach: Drupal.behaviors.chartsGooglecharts.attach}}
   */
  Drupal.behaviors.chartsGooglecharts = {
    attach: function (context) {
      // Define a fallback value for globalOptions;
      const globalOptions = drupalSettings.charts === undefined ? {
        useMaterialDesign: false,
        chartType: 'bar'
      } : drupalSettings.charts.google.global_options;
      let useMaterialDesign = globalOptions.useMaterialDesign;
      let chartType = globalOptions.chartType;
      const materialDesignPackages = ['bar', 'line', 'spline', 'scatter', 'column',];
      const packages = ['corechart', 'gauge', 'table',];
      if (useMaterialDesign === 'true' && materialDesignPackages.indexOf(chartType) !== -1) {
        if (chartType === 'spline') {
          chartType = 'line';
        }
        if (chartType === 'column') {
          chartType = 'bar';
        }
        packages.push(chartType);
      }
      // Load Google Charts API.
      google.charts.load('current', {packages: packages});

      // Re-draw charts if viewport size has been changed.
      window.addEventListener('resize', function () {
        Drupal.googleCharts.waitForFinalEvent(function () {
          // Re-draw Google Charts.
          Drupal.googleCharts.drawCharts();
        }, 200, 'reload-google-charts');
      });

      // Draw Google Charts.
      Drupal.googleCharts.drawCharts();
    }
  };

  /**
   * Helper function to draw Google Charts.
   */
  Drupal.googleCharts.drawCharts = function () {
    const contents = new Drupal.Charts.Contents();
    once('load-google-charts-item', '.charts-google').forEach(function (element) {
      if (element.dataset.hasOwnProperty('chart')) {
        const chartId = element.id;
        const dataAttributes = contents.getData(chartId);
        google.charts.setOnLoadCallback(Drupal.googleCharts.drawChart(chartId, dataAttributes['visualization'], dataAttributes['data'], dataAttributes['options']));
        if (element.nextElementSibling && element.nextElementSibling.hasAttribute('data-charts-debug-container')) {
          element.nextElementSibling.querySelector('code').innerText = JSON.stringify(dataAttributes, null, ' ');
        }
      }
    });
  };

  /**
   * Helper function to draw a Google Chart.
   *
   * @param {string} chartId - Chart Id.
   * @param {string} chartType - Chart Type.
   * @param {string} dataTable - Data.
   * @param {string} googleChartOptions - Options.
   *
   * @return {function} Draw Chart.
   */
  Drupal.googleCharts.drawChart = function (chartId, chartType, dataTable, googleChartOptions) {
    return function () {
      const data = google.visualization.arrayToDataTable(dataTable);
      const options = googleChartOptions;
      const googleChartTypeFormatted = chartType;

      let visualizationNamespace = 'visualization';
      let visualizationClass = chartType;
      // Replace the 'Spline' chart type with 'Line'.
      if (visualizationClass === 'SplineChart') {
        visualizationClass = 'LineChart';
      }
      if (options.theme === 'material') {
        // Material Design wants to use the 'charts' namespace.
        visualizationNamespace = 'charts';
        // Strip the 'Chart' suffix from the chart type.
        visualizationClass = visualizationClass.replace('Chart', '');
        // Replace the 'Column' chart type with 'Bar'.
        if (visualizationClass === 'Column') {
          visualizationClass = 'Bar';
        }
      }
      console.log(visualizationClass);

      let chart;
      switch (googleChartTypeFormatted) {
        case 'BarChart':
        case 'ColumnChart':
        case 'LineChart':
        case 'SplineChart':
        case 'ScatterChart':
          chart = new google[visualizationNamespace][visualizationClass](document.getElementById(chartId));
          break;

        case 'DonutChart':
        case 'PieChart':
          chart = new google.visualization.PieChart(document.getElementById(chartId));
          break;

        case 'BubbleChart':
          chart = new google.visualization.BubbleChart(document.getElementById(chartId));
          break;

        case 'AreaChart':
          chart = new google.visualization.AreaChart(document.getElementById(chartId));
          break;

        case 'Gauge':
          chart = new google.visualization.Gauge(document.getElementById(chartId));
          break;

        case 'ComboChart':
          chart = new google.visualization.ComboChart(document.getElementById(chartId));
          break;

        case 'GeoChart':
          chart = new google.visualization.GeoChart(document.getElementById(chartId));
          break;

        case 'TableChart':
          chart = new google.visualization.Table(document.getElementById(chartId));
      }

      let color_regex = /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/;
      if (options['colors'] && options['colors'].length > 10) {
        for (const i in options['colors']) {
          if (!color_regex.test(options['colors'][i])) {
            options['colors'][i] = '#FFFFFF';
          }
        }
      }

      // Rewrite the colorAxis item to include the colors: key
      if (typeof options['colorAxis'] != 'undefined') {
        const colors = options['colorAxis'];
        const num_colors = colors.length;
        options['colorAxis'] = colors.splice(num_colors);
        options['colorAxis'] = {colors: colors};
      }
      chart.draw(data, options);
    };
  };

  /**
   * Helper function to run a callback function once when triggering an event
   * multiple times.
   *
   * Example usage:
   * @code
   *  window.addEventListener('resize', function () {
   *    Drupal.googleCharts.waitForFinalEvent(function(){
   *      alert('Resize...');
   *    }, 500, "some unique string");
   *  });
   * @endcode
   */
  Drupal.googleCharts.waitForFinalEvent = (function () {
    let timers = {};
    return function (callback, ms, uniqueId) {
      if (!uniqueId) {
        uniqueId = "Don't call this twice without a uniqueId";
      }
      if (timers[uniqueId]) {
        clearTimeout(timers[uniqueId]);
      }
      timers[uniqueId] = setTimeout(callback, ms);
    };
  })();
}(Drupal, drupalSettings, once));
