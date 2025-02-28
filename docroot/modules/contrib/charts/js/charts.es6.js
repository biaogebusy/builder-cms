/**
 * @file
 * Charts API.
 */
((Drupal) => {

  Drupal.Charts = Drupal.Charts || {};
  Drupal.Charts.Configs = Drupal.Charts.Configs || [];

  /**
   * @typedef {class} Drupal.Charts.Contents
   */

  Drupal.Charts.Contents = class {
    constructor() {
      const chartsElements = document.querySelectorAll('[data-chart]');
      chartsElements.forEach(function (el) {
        const id = el.getAttribute('id');
        Drupal.Charts.Configs[id] = JSON.parse(el.getAttribute('data-chart'));
        Drupal.Charts.Configs[id].drupalChartDivElement = el;
        Drupal.Charts.Configs[id].drupalChartDivId = id;
      });
    }

    initialize(id) {
      const event = new CustomEvent('drupalChartsConfigsInitialization', {
        detail: Drupal.Charts.Configs[id]
      });
      Drupal.Charts.Configs[id].drupalChartDivElement.dispatchEvent(event);
    }

    static update(id, data) {
      if (Drupal.Charts.Configs.hasOwnProperty(id)) {
        Drupal.Charts.Configs[id] = data;
      }
    }

    getData(id) {
      if (Drupal.Charts.Configs.hasOwnProperty(id)) {
        this.initialize(id);
        return Drupal.Charts.Configs[id];
      }
      return {};
    }
  };
})(Drupal);
