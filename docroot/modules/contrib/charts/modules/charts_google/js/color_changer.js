/**
 * @param Drupal
 * @param once
 * @file
 * JavaScript integration for Google Charts color changer.
 */
((Drupal, once) => {
  Drupal.behaviors.chartsGoogleColorChanger = {
    attach(context) {
      const colorChangerHandler = function (event) {
        const rawData =
          this.dataset.chartsColorInfo ||
          this.dataset.chartsHighchartsColorInfo;
        if (!rawData) return;

        const metadata = JSON.parse(rawData);
        const chartData = Drupal.googleCharts.charts[metadata.chart_id];

        if (!chartData || !chartData.instance) return;

        const chart = chartData.instance;
        const { options } = chartData;
        const index = metadata.series_index;
        const newColor = event.target.value;

        if (metadata.chart_type === 'pie' || metadata.chart_type === 'donut') {
          options.slices = options.slices || {};
          options.slices[index] = options.slices[index] || {};
          options.slices[index].color = newColor;
        } else {
          options.series = options.series || {};
          options.series[index] = options.series[index] || {};
          options.series[index].color = newColor;
        }

        // Re-draw with updated options. Data remains the same.
        // We need access to the dataTable, which should also be stored.
        chart.draw(chartData.dataTable, options);
      };

      once(
        'charts-google-color-changer',
        '.charts-color-changer',
        context,
      ).forEach((element) => {
        element
          .querySelectorAll('input[type="color"]')
          .forEach((colorChanger) => {
            colorChanger.addEventListener('change', colorChangerHandler);
          });
      });
    },
  };
})(Drupal, once);
