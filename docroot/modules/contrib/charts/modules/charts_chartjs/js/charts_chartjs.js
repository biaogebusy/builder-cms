/**
 * @file
 * JavaScript integration between Chart.js and Drupal.
 */

/* global Chart, ChartDataLabels */
(function (Drupal, once) {
  Drupal.chartjsCharts = Drupal.chartjsCharts || { instances: {} };

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
          const plugins = options?.plugins;
          const hasDataLabelsDisplay = plugins?.dataLabels?.display;
          const hasAlternativeDataLabels = plugins?.datalabels;
          // Handle non-treemap charts with data labels as the primary condition.
          if (
            chart.type !== 'treemap' &&
            options.plugins &&
            (hasDataLabelsDisplay || hasAlternativeDataLabels)
          ) {
            enabledPlugins.push(ChartDataLabels);
          }
          // Handle treemap charts as the secondary condition.
          else if (chart.type === 'treemap') {
            // For treemap charts, use native treemap labels instead of dataLabels plugin.
            const dataLabelsEnabled = !!(
              hasDataLabelsDisplay || hasAlternativeDataLabels
            );
            // Configure treemap-specific labels at the element level.
            options.elements = options.elements ?? {};
            options.elements.treemap = options.elements.treemap ?? {};
            options.elements.treemap.labels =
              options.elements.treemap.labels ?? {};
            // Use the dataLabels.display setting to control treemap labels.
            options.elements.treemap.labels.display = dataLabelsEnabled;
            if (dataLabelsEnabled) {
              options.elements.treemap.labels.formatter = function (ctx) {
                // Extract the 'v' value from ctx.raw
                if (ctx.raw && typeof ctx.raw === 'object' && 'v' in ctx.raw) {
                  return ctx.raw.v;
                }
                // Fallback: if the value is in ctx.parsed.
                if (
                  ctx.parsed &&
                  typeof ctx.parsed === 'object' &&
                  'v' in ctx.parsed
                ) {
                  return ctx.parsed.v;
                }
                // Final fallback: return empty string to hide problematic labels.
                return '';
              };
            }
            // Always disable the dataLabels plugin for treemap to prevent
            // coordinate display.
            if (hasDataLabelsDisplay || hasAlternativeDataLabels) {
              options.plugins.dataLabels.display = false;
            }
          }
          // If annotations are present (e.g. the gauge chart type), add the
          // annotation plugin to the chart. The check on the global is a
          // safeguard in case the chartjs-plugin-annotation library file is
          // missing.
          if (
            options?.plugins?.annotations &&
            window['chartjs-plugin-annotation']
          ) {
            enabledPlugins.push(window['chartjs-plugin-annotation']);
          }
          Drupal.chartjsCharts.instances[chartId] = new Chart(canvas, {
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
