/**
 * @file
 * A Backbone view for a shs widget optionally rendered as chosen element.
 */

(function ($, Drupal, drupalSettings, Backbone) {

  'use strict';

  Drupal.shs_chosen = Drupal.shs_chosen || {};

  Drupal.shs_chosen.ChosenWidgetView = Drupal.shs.WidgetView.extend(/** @lends Drupal.shs_chosen.ChosenWidgetView# */{
    /**
     * @inheritdoc
     */
    render: function () {
      // Call parent render function.
      var widget = Drupal.shs.WidgetView.prototype.render.apply(this);

      // The parent method uses `fadeIn`, so remove that animation here since
      // otherwise the original select element will be visible behind the
      // Chosen widget.
      // Note, this only occurs if this bugfix is applied to the Chosen library:
      // https://github.com/harvesthq/chosen/pull/2594.
      widget.$el.stop(true);

      if (widget.container.app.getConfig('display.chosen')) {
        widget.$el.addClass('chosen-enable');
        var settings = {
          chosen: {}
        };
        $.extend(true, settings.chosen, widget.container.app.getConfig('display.chosen'));
        // Attach chosen behavior.
        Drupal.behaviors.chosen.attach(widget.container.$el, settings);
      }

      // Return self for chaining.
      return widget;
    }
  });

}(jQuery, Drupal, drupalSettings, Backbone));
