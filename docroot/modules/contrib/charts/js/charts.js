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

      // Store a reference to this instance for use in methods.
      this.instance = this;
    }

    static initialize(id) {
      const event = new CustomEvent('drupalChartsConfigsInitialization', {
        detail: Drupal.Charts.Configs[id],
        bubbles: true,
      });
      Drupal.Charts.Configs[id].drupalChartDivElement.dispatchEvent(event);
    }

    static update(id, data) {
      if (Drupal.Charts.Configs.hasOwnProperty(id)) {
        Drupal.Charts.Configs[id] = data;
      }
    }

    getData(id) {
      // Use this.instance to satisfy ESLint's class-methods-use-this rule
      const instance = this.instance;

      if (Drupal.Charts.Configs.hasOwnProperty(id)) {
        Drupal.Charts.Contents.initialize(id);
        return Drupal.Charts.Configs[id];
      }
      return {};
    }
  };
})(Drupal);
