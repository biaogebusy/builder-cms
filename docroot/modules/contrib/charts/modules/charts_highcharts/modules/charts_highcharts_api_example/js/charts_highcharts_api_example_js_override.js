(function (Drupal) {
  Drupal.charts_highcharts_api_example =
    Drupal.charts_highcharts_api_example || {};
  Drupal.charts_highcharts_api_example.highchartsTooltipFormatter =
    function () {
      return `The value for <b>${this.x}</b> is <b>${this.y}</b>`;
    };

  Drupal.behaviors.chartsHighchartsApiExampleCharts = {
    attach() {
      const chartContainer = document.getElementById('exampleidjs');
      if (!chartContainer) {
        return;
      }
      chartContainer.addEventListener(
        'drupalChartsConfigsInitialization',
        function (e) {
          const data = e.detail;
          const id = data.drupalChartDivId;
          // Change the background of the chart to green.
          data.chart.backgroundColor = 'green';
          // Replace the tooltip formatter.
          data.tooltip.formatter =
            Drupal.charts_highcharts_api_example.highchartsTooltipFormatter;
          Drupal.Charts.Contents.update(id, data);
        },
      );
    },
  };
})(Drupal);
