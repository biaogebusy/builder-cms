/**
 * @file
 * JavaScript integration between Google and Drupal.
 */

/* global google */
(function (Drupal, drupalSettings, once) {
  Drupal.googleCharts = Drupal.googleCharts || { charts: [] };

  /**
   * Behavior to initialize Google Charts.
   *
   * @type {{attach: Drupal.behaviors.chartsGooglecharts.attach}}
   */
  Drupal.behaviors.chartsGooglecharts = {
    attach() {
      // Define a fallback value for globalOptions;
      const globalOptions =
        drupalSettings.charts === undefined
          ? {
              useMaterialDesign: false,
              chartType: 'bar',
            }
          : drupalSettings.charts.google.global_options;
      const useMaterialDesign = globalOptions.useMaterialDesign;
      let chartType = globalOptions.chartType;
      const materialDesignPackages = [
        'bar',
        'line',
        'spline',
        'scatter',
        'column',
      ];
      const packages = ['corechart', 'gauge', 'table'];
      if (
        useMaterialDesign === 'true' &&
        materialDesignPackages.indexOf(chartType) !== -1
      ) {
        if (chartType === 'spline') {
          chartType = 'line';
        }
        if (chartType === 'column') {
          chartType = 'bar';
        }
        packages.push(chartType);
      }
      // Load Google Charts API.
      google.charts.load('current', { packages });

      // Re-draw charts if viewport size has been changed.
      window.addEventListener('resize', function () {
        Drupal.googleCharts.waitForFinalEvent(
          function () {
            // Re-draw Google Charts.
            Drupal.googleCharts.drawCharts();
          },
          200,
          'reload-google-charts',
        );
      });

      // Draw Google Charts.
      Drupal.googleCharts.drawCharts();
    },
  };

  /**
   * Helper function to draw Google Charts.
   */
  Drupal.googleCharts.drawCharts = function () {
    const contents = new Drupal.Charts.Contents();
    once('load-google-charts-item', '.charts-google').forEach(
      function (element) {
        if (element.dataset.hasOwnProperty('chart')) {
          const chartId = element.id;
          const dataAttributes = contents.getData(chartId);
          google.charts.setOnLoadCallback(
            Drupal.googleCharts.drawChart(
              chartId,
              dataAttributes.visualization,
              dataAttributes.data,
              dataAttributes.options,
            ),
          );
          if (
            element.nextElementSibling &&
            element.nextElementSibling.hasAttribute(
              'data-charts-debug-container',
            )
          ) {
            element.nextElementSibling.querySelector('code').innerText =
              JSON.stringify(dataAttributes, null, ' ');
          }
        }
      },
    );
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
  Drupal.googleCharts.drawChart = function (
    chartId,
    chartType,
    dataTable,
    googleChartOptions,
  ) {
    return function () {
      // If we're dealing with a box plot using a LineChart and the data
      // is provided as an array with 6 columns in this order:
      // ['X Value', 'Min', 'First Quartile', 'Median', 'Third Quartile', 'Max'],
      // then transform it into a new table that:
      // 1. Reorders the columns so that the median becomes the primary (non-interval) value.
      // 2. Appends new columns for interval data.
      // The final header will be:
      // [
      //   "X Value",
      //   "Median",
      //   "Min",
      //   "First Quartile",
      //   "Third Quartile",
      //   "Max",
      //   { label: "max", role: "interval", type: "number" },
      //   { label: "min", role: "interval", type: "number" },
      //   { label: "firstQuartile", role: "interval", type: "number" },
      //   { label: "median", role: "interval", type: "number" },
      //   { label: "thirdQuartile", role: "interval", type: "number" }
      // ]
      // And each data row transforms accordingly.
      if (
        chartType === 'LineChart' &&
        Array.isArray(dataTable) &&
        dataTable[0].length === 6
      ) {
        const oldHeader = dataTable[0];
        // Rearrange the header without adding a duplicate column.
        // New header: [ "X Value", "Median", "Min", "First Quartile", "Third Quartile", "Max" ]
        let newHeader = [
          oldHeader[0], // X Value
          oldHeader[3], // Median becomes the primary series
          oldHeader[1], // Min
          oldHeader[2], // First Quartile
          oldHeader[4], // Third Quartile
          oldHeader[5], // Max
        ];
        // Append new columns for interval data.
        newHeader = newHeader.concat([
          { label: 'max', role: 'interval', type: 'number' },
          { label: 'min', role: 'interval', type: 'number' },
          { label: 'firstQuartile', role: 'interval', type: 'number' },
          { label: 'median', role: 'interval', type: 'number' },
          { label: 'thirdQuartile', role: 'interval', type: 'number' },
        ]);
        dataTable[0] = newHeader;
        // Transform each data row.
        // Original row format: [X, Min, First Quartile, Median, Third Quartile, Max]
        // New row format: [X, Median, Min, First Quartile, Third Quartile,
        // Max, max, min, firstQuartile, median, thirdQuartile]
        // Where the interval values are:
        //   max: original index 5,
        //   min: original index 1,
        //   firstQuartile: original index 2,
        //   median: original index 3,
        //   thirdQuartile: original index 4.
        for (let i = 1; i < dataTable.length; i++) {
          const oldRow = dataTable[i];
          let newRow = [
            oldRow[0],
            oldRow[3],
            oldRow[1],
            oldRow[2],
            oldRow[4],
            oldRow[5],
          ];
          newRow = newRow.concat([
            oldRow[5],
            oldRow[1],
            oldRow[2],
            oldRow[3],
            oldRow[4],
          ]);
          dataTable[i] = newRow;
        }
      }
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

      let chart;
      switch (googleChartTypeFormatted) {
        case 'BarChart':
        case 'ColumnChart':
        case 'LineChart':
        case 'SplineChart':
        case 'ScatterChart':
          chart = new google[visualizationNamespace][visualizationClass](
            document.getElementById(chartId),
          );
          break;

        case 'DonutChart':
        case 'PieChart':
          chart = new google.visualization.PieChart(
            document.getElementById(chartId),
          );
          break;

        case 'BubbleChart':
          chart = new google.visualization.BubbleChart(
            document.getElementById(chartId),
          );
          break;

        case 'CandlestickChart':
          chart = new google.visualization.CandlestickChart(
            document.getElementById(chartId),
          );
          break;

        case 'AreaChart':
          chart = new google.visualization.AreaChart(
            document.getElementById(chartId),
          );
          break;

        case 'Gauge':
          chart = new google.visualization.Gauge(
            document.getElementById(chartId),
          );
          break;

        case 'ComboChart':
          chart = new google.visualization.ComboChart(
            document.getElementById(chartId),
          );
          break;

        case 'GeoChart':
          chart = new google.visualization.GeoChart(
            document.getElementById(chartId),
          );
          break;

        case 'TableChart':
          chart = new google.visualization.Table(
            document.getElementById(chartId),
          );
      }

      const colorRegex = /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/;
      if (options.colors && options.colors.length > 10) {
        Object.keys(options.colors).forEach((i) => {
          if (!colorRegex.test(options.colors[i])) {
            options.colors[i] = '#FFFFFF';
          }
        });
      }

      // Rewrite the colorAxis item to include the colors: key
      if (typeof options.colorAxis !== 'undefined') {
        const colors = options.colorAxis;
        const numColors = colors.length;
        options.colorAxis = colors.splice(numColors);
        options.colorAxis = { colors };
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
    const timers = {};
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
})(Drupal, drupalSettings, once);
