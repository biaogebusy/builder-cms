/**
 * @file
 * Applies textures to Highcharts charts.
 */

/* global Highcharts */
(function (Drupal, once) {
  Drupal.charts_highcharts = Drupal.charts_highcharts || {};

  Drupal.behaviors.chartsHighchartsAddTexture = {
    attach(context) {
      once('charts-highchart-texture', '.charts-highchart', context).forEach(
        function (element) {
          element.addEventListener(
            'drupalChartsConfigsInitialization',
            function (e) {
              const data = e.detail;
              const id = data.drupalChartDivId;
              // Add textures to series.
              if (
                'series' in data &&
                data.series[0].color !== undefined &&
                typeof data.series[0].data[0] === 'number'
              ) {
                for (let i = 0; i < data.series.length; i++) {
                  data.series[i].color = Drupal.charts_highcharts.getPattern(
                    Drupal.charts_highcharts.getUnderTenIndex(i),
                    data.series[i].color,
                  );
                }
              } else if (
                'series' in data &&
                typeof data.series[0].data[0] === 'object' &&
                data.series[0].data[0].color !== undefined
              ) {
                for (let i = 0; i < data.series[0].data.length; i++) {
                  data.series[0].data[i].color =
                    Drupal.charts_highcharts.getPattern(
                      Drupal.charts_highcharts.getUnderTenIndex(i),
                      data.series[0].data[i].color,
                    );
                }
              }
              if (
                'series' in data &&
                typeof data.series[0].data[0] === 'object' &&
                data.series[0].data[0].color === undefined
              ) {
                for (let i = 0; i < data.colors.length; i++) {
                  data.colors[i] = Drupal.charts_highcharts.getPattern(
                    Drupal.charts_highcharts.getUnderTenIndex(i),
                    data.colors[i],
                  );
                }
              }

              Drupal.Charts.Contents.update(id, data);
            },
          );
        },
      );
    },
  };

  /**
   * Get under ten index in case there are more than ten series because
   * Highcharts patterns array has ten patterns
   * 0 - 9 index.
   *
   * @param {number} k
   *  The index to check.
   *
   * @return {number}
   *  The under ten index.
   */
  Drupal.charts_highcharts.getUnderTenIndex = function (k) {
    if (k < 10) {
      return k;
    }
    while (k >= 10) {
      // Sum the digits of k.
      k = k
        .toString()
        .split('')
        .reduce(function (a, b) {
          return parseInt(a, 10) + parseInt(b, 10);
        });
    }
    return k;
  };

  /**
   * Get a default pattern, but using the series color.
   * The index-argument refers to which default pattern to use
   *
   * @param {number} index
   *   The index of the pattern to use.
   * @param {string} color
   *  The color to use for the pattern.
   *
   * @return {object}
   *   The pattern object to use for the series.
   */
  Drupal.charts_highcharts.getPattern = function (index, color) {
    return {
      pattern: Highcharts.merge(Highcharts.patterns[index], {
        color,
      }),
    };
  };
})(Drupal, once);
