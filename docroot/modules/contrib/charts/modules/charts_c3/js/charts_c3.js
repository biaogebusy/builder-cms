/**
 * @file
 * JavaScript's integration between C3 and Drupal.
 */
(function (Drupal, once) {

  'use strict';

  Drupal.behaviors.chartsC3 = {
    attach: function (context, settings) {
      const contents = new Drupal.Charts.Contents();
      once('charts-c3', '.charts-c3', context).forEach(function (element) {
        const config = contents.getData(element.id);
        c3.generate(config);
        if (element.nextElementSibling && element.nextElementSibling.hasAttribute('data-charts-debug-container')) {
          element.nextElementSibling.querySelector('code').innerText = JSON.stringify(config, null, ' ');
        }
      });
    }
  };
}(Drupal, once));
