/**
 * @file
 * JavaScript integration between Billboard and Drupal.
 */
(function (Drupal, once) {

  'use strict';

  Drupal.behaviors.chartsBillboard = {
    attach: function (context, settings) {
      const contents = new Drupal.Charts.Contents();
      once('charts-billboard', '.charts-billboard', context).forEach(function (element) {
        const config = contents.getData(element.id);
        const title = config.title.text;
        // If the title contains '\\n', convert it to a line break.
        if (title.indexOf('\\n') !== -1) {
          config.title.text = title.replace(/\\n/g, '\n');
        }
        bb.generate(config);
        if (element.nextElementSibling && element.nextElementSibling.hasAttribute('data-charts-debug-container')) {
          element.nextElementSibling.querySelector('code').innerText = JSON.stringify(config, null, ' ');
        }
      });
    }
  };
}(Drupal, once));
