/**
 * @file
 * JavaScript's integration between Highcharts and Drupal.
 */
((Drupal, once) => {
  'use strict';

  Drupal.behaviors.chartsHighchartsColorChanger = {
    attach: function (context) {
      const colorChangerHandler = function (event) {
        const chartMetadata = JSON.parse(this.dataset.chartsHighchartsColorInfo);
        const chartsElement = document.getElementById(chartMetadata.chart_id);
        const chart = Highcharts.charts[chartsElement.dataset.highchartsChart];
        switch (chartMetadata.chart_type) {
          case 'pie':
            chart.series[0].data[chartMetadata.series_index].color = '';
            chart.series[0].data[chartMetadata.series_index].update({
              color: event.target.value
            });
            break;

          case 'gauge':
            chart.yAxis[0].plotLinesAndBands[0].options.color = event.target.value;
            chart.yAxis[0].update();
            break;

          default:
            chart.series[chartMetadata.series_index].update({
              color: event.target.value
            });
        }
      };
      once('charts-color-changer', '.charts-color-changer', context).forEach(function (element) {
        element.querySelectorAll('input[type="color"]').forEach(
          (colorChanger) => {
            colorChanger.addEventListener('change', colorChangerHandler);
          }
        );
      });
    }
  };
})(Drupal, once);
