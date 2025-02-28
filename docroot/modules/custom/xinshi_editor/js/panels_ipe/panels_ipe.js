/**
 * @file
 */

(function ($, _, Backbone, Drupal) {

  'use strict';

  Drupal.behaviors.panels_ipe_builder = {
    attach: function (context, settings) {
      if (settings['panels_ipe']['unsaved_changes']) {
        delete settings['panels_ipe']['unsaved_changes'];
        Drupal.panels_ipe.app.set('unsaved', true);
      }

      $('.ui-tooltip').remove();

      $('#panels-ipe-block-type-form-wrapper').tooltip({
        items: 'input[data-src]',
        position: {my: 'bottom', at: 'top-10'},
        content: function () {return '<img src="' + $(this).attr('data-src') + '">';}
      });
    }
  };

  Drupal.panels_ipe_builder = Drupal.panels_ipe_builder || {};

  /**
   * @namespace
   */
  Drupal.panels_ipe_builder.panels_ipe = {};

  /**
   * Make customizations to the Panels IPE.
   */
  Backbone.on('PanelsIPEInitialized', function () {
    Drupal.panels_ipe.app_view.tabsView.collection.remove('revert');

    if(drupalSettings.panels_ipe.panels_display.storage_id.match('alert_template')){
      Drupal.panels_ipe.app_view.tabsView.collection.remove('change_layout');
    }

    Drupal.panels_ipe.app_view.stopListening(Drupal.panels_ipe.app, 'addContentBlock');
    Drupal.panels_ipe.app_view.listenTo(Drupal.panels_ipe.app, 'addContentBlock', function (newBlockData) {
      var block = new Drupal.panels_ipe.BlockPluginModel(newBlockData);
      // block.btemplate = drupalSettings.panelizer.entity.entity_type_id || 'alert_template';

      if(typeof drupalSettings.panels_ipe.builder_new_block_content != 'undefined') {
        block = new Drupal.panels_ipe.BlockPluginModel(drupalSettings.panels_ipe.builder_new_block_content);
        delete drupalSettings.panels_ipe.builder_new_block_content;
      }
      var info = {
        url: Drupal.panels_ipe.urlRoot(drupalSettings) + '/block_plugins/' + block.get('plugin_id') + '/form',
        model: block
      };
      this.tabsView.collection.get('manage_content').set('active', false);
      this.loadBlockForm(info);
    });

    Drupal.panels_ipe.app_view.stopListening(Drupal.panels_ipe.app, 'editContentBlock');
    Drupal.panels_ipe.app_view.listenTo(Drupal.panels_ipe.app, 'editContentBlock', function (block) {
      var plugin_split = block.get('id').split(':');
      var info = {
        url: Drupal.panels_ipe.urlRoot(drupalSettings) + '/block_content/edit/block/' + block.get('uuid') + '/' + plugin_split[1] + '/form',
        model: block
      };
      this.loadBlockForm(info, this.template_content_block_edit);
    });

    Drupal.panels_ipe.app_view.tabsView.tabViews['manage_content'] = new Drupal.panels_ipe_builder.BlockPicker();

    Drupal.panels_ipe.app.get('layout').get('regionCollection').each(function (region) {
      region.get('blockCollection').each(function (block) {
      });
    });




    Drupal.panels_ipe.CategoryView.prototype.template = _.template(
      '<div class="ipe-category-picker-top"></div>' +
        '<div class="ipe-category-picker-bottom" tabindex="-1">' +
        '  <div class="ipe-categories"></div>' +
        '</div>'
    );

    Drupal.panels_ipe.BlockView.prototype.initialize = function (options) {

      this.model = options.model;
      // An element already exists and our HTML properly isn't set.
      // This only occurs on initial page load for performance reasons.
      if(!this.model.get('btemplate')){
        this.model.set('btemplate', drupalSettings.panelizer.entity.entity_type_id || 'alert_template');
      }

      if (options.el && !this.model.get('html')) {
        this.model.set({html: this.$el.prop('outerHTML')});
      }
      this.listenTo(this.model, 'sync', this.finishedSync);
      this.listenTo(this.model, 'change:syncing', this.render);
    };

    Drupal.panels_ipe.BlockView.prototype.template_actions =  _.template(
    '<% if (data.btemplate === "alert_template") { %>' +
      '<caption>' +
    '<% } %>' +
      '<div class="ipe-actions-block ipe-actions" data-block-action-id="<%- data.uuid %>" data-block-edit-id="<%- data.id %>">' +
      '  <h5>' + Drupal.t('Block: <%- data.label %>') + '</h5>' +
      '  <ul class="ipe-action-list">' +
      '    <li data-action-id="remove">' +
      '      <a><span class="ipe-icon ipe-icon-remove"></span></a>' +
      '    </li>' +
      '    <li data-action-id="up">' +
      '      <a><span class="ipe-icon ipe-icon-up"></span></a>' +
      '    </li>' +
      '    <li data-action-id="down">' +
      '      <a><span class="ipe-icon ipe-icon-down"></span></a>' +
      '    </li>' +
      '    <li data-action-id="move">' +
      '      <select><option>' + Drupal.t('Move') + '</option></select>' +
      '    </li>' +
      '    <li data-action-id="configure">' +
      '      <a><span class="ipe-icon ipe-icon-configure"></span></a>' +
      '    </li>' +
      '<% if (data.plugin_id === "block_content" && data.edit_access) { %>' +
      '    <li data-action-id="edit-content-block">' +
      '      <a><span class="ipe-icon ipe-icon-edit"></span></a>' +
      '    </li>' +
      '<% } %>' +
      '  </ul>' +
      '</div>' +
    '<% if (data.btemplate === "alert_template") { %>' +
      '</caption>' + //, { 'variable': 'data' }
    '<% } %>', { 'variable': 'data' }
    );

  });
}(jQuery, _, Backbone, Drupal));
