/**
 * @file
 */

(function ($, _, Backbone, Drupal) {

  'use strict';

  Drupal.picl = Drupal.picl || {};

  /**
   * @namespace
   */
  Drupal.picl.panels_ipe = {};

  /**
   * Make customizations to the Panels IPE.
   */
  Backbone.on('PanelsIPEInitialized', function() {
    var entity = drupalSettings.panelizer.entity;

    var start_tab = new Drupal.panels_ipe.TabModel({title: Drupal.t('Start Edit'), id: 'start', hidden: true});
    Drupal.panels_ipe.app_view.tabsView.collection.add(start_tab);
    Drupal.panels_ipe.app_view.listenTo(start_tab, 'change:active', function () {
      if (start_tab.get('active') && !start_tab.get('loading')) {
        start_tab.set({loading: true});
        $.ajax({
          url: drupalSettings.path.baseUrl + 'admin/picl/panels_ipe/' + entity.entity_type_id + '/' + entity.entity_id + '/locking',
          data: {},
          type: 'POST'
        }).done(function (data) {
          start_tab.set('loading', false);
          Drupal.panels_ipe.app_view.model.set('locked', data);
        });
      }
    });

    var unlock_tab = new Drupal.panels_ipe.TabModel({title: Drupal.t('Unlock'), id: 'unlock', hidden: true});
    Drupal.panels_ipe.app_view.tabsView.collection.add(unlock_tab);
    Drupal.panels_ipe.app_view.listenTo(unlock_tab, 'change:active', function () {
      if (unlock_tab.get('active') && !unlock_tab.get('loading')) {
        unlock_tab.set({loading: true});
        $.ajax({
          url: drupalSettings.path.baseUrl + 'admin/picl/panels_ipe/' + entity.entity_type_id + '/' + entity.entity_id + '/unlock',
          data: {},
          type: 'POST'
        }).done(function (data) {
          unlock_tab.set('loading', false);
          Drupal.panels_ipe.app_view.model.set('locked', false);
        });
      }
    });
    
    // start edit button
    Drupal.panels_ipe.app_view.tabsView.collection.each(function(tab) {tab.set('hidden', true);});
    start_tab.set('hidden', false);
    Drupal.panels_ipe.app_view.render(false);

    Drupal.panels_ipe.app_view.listenTo(Drupal.panels_ipe.app_view.model, 'change:locked', function() {
      var locked = Drupal.panels_ipe.app_view.model.get('locked');
      if (locked) {
        if (locked.self) {
          // unlock button
          start_tab.set('hidden', true);
          Drupal.panels_ipe.app_view.tabsView.collection.get('change_layout').set('hidden', false);
          Drupal.panels_ipe.app_view.tabsView.collection.get('manage_content').set('hidden', false);
          Drupal.panels_ipe.app_view.tabsView.collection.get('edit').set('hidden', false);
          unlock_tab.set('hidden', Drupal.panels_ipe.app.get('unsaved'));
          Drupal.panels_ipe.app_view.render(false);
        } else {
          Drupal.panels_ipe.app_view.tabsView.$el.html(locked.info);
        }
      } else {
        // start edit button
        Drupal.panels_ipe.app_view.tabsView.collection.each(function(tab) {tab.set('hidden', true);});
        start_tab.set('hidden', false);
        Drupal.panels_ipe.app_view.render(false);
      }
    });

    Drupal.panels_ipe.app_view.listenTo(Drupal.panels_ipe.app_view.model, 'change:unsaved', function() {
      unlock_tab.set('hidden', Drupal.panels_ipe.app.get('unsaved'));
      Drupal.panels_ipe.app_view.render(false);
    });

    $.ajax({
      url: drupalSettings.path.baseUrl + 'admin/picl/panels_ipe/' + entity.entity_type_id + '/' + entity.entity_id +'/locked'
    }).done(function (data) {
      Drupal.panels_ipe.app_view.model.set('locked', data);
    });
  });

}(jQuery, _, Backbone, Drupal));
