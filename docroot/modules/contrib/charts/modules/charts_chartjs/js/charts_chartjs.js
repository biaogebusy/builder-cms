/**
 * @file
 * JavaScript integration between Chart.js and Drupal.
 */

/* global Chart, ChartDataLabels */
(function (Drupal, once) {
  function copyAttributes(source, target) {
    return Array.from(source.attributes).forEach((attribute) => {
      target.setAttribute(
        attribute.nodeName === 'id' ? 'data-id' : attribute.nodeName,
        attribute.nodeValue,
      );
    });
  }

  Drupal.behaviors.chartsChartjs = {
    attach(context) {
      const contents = new Drupal.Charts.Contents();
      once('load-charts-chartjs', '.charts-chartjs', context).forEach(
        function (element) {
          const chartId = element.id;
          // Switching div for canvas element.
          const parent = element.parentNode;
          const canvas = document.createElement('canvas');
          // Transferring the attributes of our source element to the canvas.
          copyAttributes(element, canvas);
          canvas.id = chartId;
          parent.replaceChild(canvas, element);

          // Initializing the chart item.
          const chart = contents.getData(chartId);
          const options = chart.options;
          const enabledPlugins = [];
          // If options.plugins.datalabels is set, we need to add the plugin to the chart.
          if (options.plugins && options.plugins.datalabels) {
            enabledPlugins.push(ChartDataLabels);
          }
          new Chart(chartId, {
            type: chart.type,
            data: chart.data,
            plugins: enabledPlugins,
            options,
          });
          if (
            canvas.nextElementSibling &&
            canvas.nextElementSibling.hasAttribute(
              'data-charts-debug-container',
            )
          ) {
            canvas.nextElementSibling.querySelector('code').innerText =
              JSON.stringify(chart, null, ' ');
          }
        },
      );
    },
  };
})(Drupal, once);
