/**
 * @file
 * JavaScript integration between Billboard and Drupal.
 */

/* global bb */
(function (Drupal, once) {
  // Create a registry to store Billboard instances.
  Drupal.billboardCharts = Drupal.billboardCharts || { instances: {} };

  Drupal.behaviors.chartsBillboard = {
    attach(context) {
      const contents = new Drupal.Charts.Contents();
      once('charts-billboard', '.charts-billboard', context).forEach(
        function (element) {
          const chartId = element.id;
          const config = contents.getData(chartId);
          if (config.title && config.title.text) {
            const title = config.title.text;
            if (title.includes('\\n')) {
              config.title.text = title.replace(/\\n/g, '\n');
            }
          }
          // Store the generated instance in our registry.
          Drupal.billboardCharts.instances[chartId] = bb.generate(config);

          if (
            element.nextElementSibling &&
            element.nextElementSibling.hasAttribute(
              'data-charts-debug-container',
            )
          ) {
            element.nextElementSibling.querySelector('code').innerText =
              JSON.stringify(config, null, ' ');
          }
        },
      );
    },
  };
})(Drupal, once);
