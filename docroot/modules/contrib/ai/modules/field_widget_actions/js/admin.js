/**
 * @file
 * Contains javascript functionality for the search api decoupled admin ui.
 */

(function (window, Drupal, once) {

  'use strict';

  Drupal.fieldWidgetActions = Drupal.fieldWidgetActions || {};

  Drupal.fieldWidgetActions.moveElement = function (evt) {
    const element = evt.item;
    element.closest('.field-widget-actions-sortable').querySelectorAll('.field-widget-action-element-order-weight').forEach(function (el, index) {
      el.value = index;
    });
  }

  Drupal.behaviors.fieldWidgetActionsAdminUi = {
    attach: function (context, settings) {
      const elements = once('field-widget-actions-admin-ui-processed', '.field-widget-actions-sortable', context);
      elements.forEach(function (element) {
        new Sortable(element, {
          animation: 150,
          draggable: '.field-widget-action-element',
          handle: '.field-widget-action-element summary',
          onEnd: function (evt) {
            Drupal.fieldWidgetActions.moveElement(evt);
          }
        });
      });
    }
  }

})(window, Drupal, once);
