/**
 * @file
 * JavaScript integration for Billboard.js color changer.
 */

/* global bb */
((Drupal, once) => {
  Drupal.behaviors.chartsBillboardColorChanger = {
    attach(context) {
      const colorChangerHandler = function (event) {
        const rawData =
          this.dataset.chartsColorInfo ||
          this.dataset.chartsHighchartsColorInfo;
        if (!rawData) return;

        const metadata = JSON.parse(rawData);
        const chartId = metadata.chart_id;

        // Retrieve the instance from our Drupal-specific registry.
        const chart = Drupal.billboardCharts.instances[chartId];
        if (!chart) return;

        const newColor = event.target.value;
        // Billboard uses the series ID (name) for color updates.
        const seriesName = metadata.series_name;

        const colorUpdate = {};
        colorUpdate[seriesName] = newColor;

        // Use the Billboard API to update colors.
        chart.data.colors(colorUpdate);
      };

      once(
        'charts-billboard-color-changer',
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
