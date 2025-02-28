/**
 * @file
 * A Backbone View that controls the overall Simple hierarchical select widgets.
 *
 * @see Drupal.shs.AppModel
 */

(function ($, _, Backbone, Drupal, once) {

  'use strict';

  Drupal.shs_chosen = Drupal.shs_chosen || {};

  Drupal.shs_chosen.ChosenAppView = Drupal.shs.AppView.extend(/** @lends Drupal.shs_chosen.ChosenAppView# */{
    /**
     * @inherit
     */
    initialize: function (options) {
      // Track app state.
      this.config = this.model.get('config');

      // Initialize collection.
      this.collection = new Drupal.shs.ContainerCollection();
      this.collection.reset();

      // Initialize event listeners.
      this.listenTo(this.collection, 'initialize:shs', this.renderWidgets);

      $(once("shs", this.$el))
              .addClass('hidden')
              .addClass('chosen-disable');
    }
  });

}(jQuery, _, Backbone, Drupal, once));
