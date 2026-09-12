/* global c3 */
(function (Drupal, once) {
  // Create a registry to store C3 instances.
  Drupal.c3Charts = Drupal.c3Charts || { instances: {} };

  Drupal.behaviors.chartsC3 = {
    attach(context) {
      const contents = new Drupal.Charts.Contents();
      once('charts-c3', '.charts-c3', context).forEach(function (element) {
        const chartId = element.id;
        const config = contents.getData(chartId);

        // Store the generated instance in our registry.
        Drupal.c3Charts.instances[chartId] = c3.generate(config);

        if (
          element.nextElementSibling &&
          element.nextElementSibling.hasAttribute('data-charts-debug-container')
        ) {
          element.nextElementSibling.querySelector('code').innerText =
            JSON.stringify(config, null, ' ');
        }
      });
    },
  };
})(Drupal, once);
