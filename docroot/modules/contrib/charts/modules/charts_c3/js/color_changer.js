/**
 * @file
 * JavaScript integration for C3 color changer.
 */

/* global c3 */
((Drupal, once) => {
  Drupal.behaviors.chartsC3ColorChanger = {
    attach(context) {
      const colorChangerHandler = function (event) {
        const rawData = this.dataset.chartsColorInfo;
        if (!rawData) return;

        const metadata = JSON.parse(rawData);
        const chartId = metadata.chart_id;

        // Retrieve the instance from our registry.
        const chart = Drupal.c3Charts.instances[chartId];
        if (!chart) return;

        const newColor = event.target.value;
        const seriesName = metadata.series_name;

        const colorUpdate = {};
        colorUpdate[seriesName] = newColor;

        // C3 dynamic color update.
        chart.data.colors(colorUpdate);
      };

      once('charts-c3-color-changer', '.charts-color-changer', context).forEach(
        (element) => {
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
