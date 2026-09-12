/**
 * @file
 * JavaScript integration for Chart.js color changer.
 */

/* global Chart */
((Drupal, once) => {
  Drupal.behaviors.chartsChartjsColorChanger = {
    attach(context) {
      const colorChangerHandler = function (event) {
        // Retrieve metadata passed from the PHP form.
        const info =
          this.dataset.chartsColorInfo ||
          this.dataset.chartsHighchartsColorInfo;
        const chartMetadata = JSON.parse(info);
        const chartsElement = document.getElementById(chartMetadata.chart_id);

        // Chart.js renders on a canvas. Find it within the wrapper.
        const canvas =
          chartsElement.tagName === 'CANVAS'
            ? chartsElement
            : chartsElement.querySelector('canvas');

        const chart = Chart.getChart(canvas);
        if (!chart) return;

        const newColor = event.target.value;
        const seriesIndex = chartMetadata.series_index;

        // Map updates based on chart type.
        if (
          chartMetadata.chart_type === 'pie' ||
          chartMetadata.chart_type === 'doughnut'
        ) {
          // Pie/Donut: Index refers to an item in the first dataset's color array.
          chart.data.datasets[0].backgroundColor[seriesIndex] = newColor;
        } else {
          // Bar/Line: Index refers to the dataset itself.
          chart.data.datasets[seriesIndex].backgroundColor = newColor;
          chart.data.datasets[seriesIndex].borderColor = newColor;
        }

        // Trigger the animation update.
        chart.update();
      };

      once(
        'charts-chartjs-color-changer',
        '.charts-color-changer',
        context,
      ).forEach(function (element) {
        element
          .querySelectorAll('input[type="color"]')
          .forEach((colorChanger) => {
            colorChanger.addEventListener('change', colorChangerHandler);
          });
      });
    },
  };
})(Drupal, once);
