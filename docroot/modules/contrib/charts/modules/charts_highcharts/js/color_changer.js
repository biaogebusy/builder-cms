/**
 * @file
 * JavaScript's integration between Highcharts and Drupal.
 */

/* global Highcharts */
((Drupal, once) => {
  Drupal.behaviors.chartsHighchartsColorChanger = {
    attach(context) {
      const colorChangerHandler = function (event) {
        // Use event.target to reference the specific color input that changed.
        const target = event.target;

        // Fetch metadata from the new generic attribute or fallback to the legacy one.
        const rawData =
          target.dataset.chartsColorInfo ||
          target.dataset.chartsHighchartsColorInfo;

        if (!rawData) {
          return;
        }

        try {
          const chartMetadata = JSON.parse(rawData);
          const chartsElement = document.getElementById(chartMetadata.chart_id);

          if (!chartsElement || !chartsElement.dataset.highchartsChart) {
            return;
          }

          const chart =
            Highcharts.charts[chartsElement.dataset.highchartsChart];

          if (!chart) {
            return;
          }

          const newValue = event.target.value;

          switch (chartMetadata.chart_type) {
            case 'pie':
              // Resetting color to empty string allows the update to take effect cleanly.
              chart.series[0].data[chartMetadata.series_index].color = '';
              chart.series[0].data[chartMetadata.series_index].update({
                color: newValue,
              });
              break;

            case 'gauge':
              // Gauges in Highcharts often use plotBands for color zones.
              if (chart.yAxis[0].plotLinesAndBands[0]) {
                chart.yAxis[0].plotLinesAndBands[0].options.color = newValue;
                chart.yAxis[0].update();
              }
              break;

            default:
              // Standard update for Bar, Line, Column, etc.
              chart.series[chartMetadata.series_index].update({
                color: newValue,
              });
          }
        } catch (e) {
          console.error(
            'Charts Highcharts: Failed to parse color metadata.',
            e,
          );
        }
      };

      // Attach the listener to all color inputs within the color changer wrapper.
      once('charts-color-changer', '.charts-color-changer', context).forEach(
        function (element) {
          element
            .querySelectorAll('input[type="color"]')
            .forEach((colorChanger) => {
              colorChanger.addEventListener('change', colorChangerHandler);
            });
        },
      );
    },
  };
})(Drupal, once);
