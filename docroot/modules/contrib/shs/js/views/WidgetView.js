/**
 * @file
 * A Backbone view for a shs widget.
 */

(function ($, Drupal, drupalSettings, Backbone) {

  'use strict';

  Drupal.shs.WidgetView = Backbone.View.extend(/** @lends Drupal.shs.WidgetView# */{

    /**
     * The enclosing container.
     *
     * @type {Drupal.shs.ContainerView}
     */
    container: null,
    /**
     * Default tagname of this view.
     *
     * @type {string}
     */
    tagName: 'select',
    /**
     * List of custom events.
     */
    events: {
      'change': 'selectionChange'
    },
    /**
     * Backbone View for shs widgets.
     *
     * @constructs
     *
     * @augments Backbone.View
     */
    initialize: function (options) {
      this.container = options.container;

      if (!this.model.get('dataLoaded')) {
        // Create new item collection.
        this.model.itemCollection = new Drupal.shs.WidgetItemCollection({
          url: Drupal.url(this.container.app.getConfig('baseUrl') + '/' + this.container.app.getConfig('fieldName') + '/' + this.container.app.getConfig('bundle') + '/' + this.model.get('id'))
        });
      }

      this.listenTo(this, 'widget:rerender', this.render);
      this.listenTo(this.model.itemCollection, 'update', this.render);

      if (this.model.get('dataLoaded')) {
        // Re-render widget without fetching.
        this.trigger('widget:rerender');
      }
      else {
        // Fetch collection items.
        this.model.itemCollection.fetch();
      }
    },
    /**
     * @inheritdoc
     */
    render: function () {
      var widget = this;
      var elemId = widget.container.app.$el.prop('id') + '-shs-' + widget.container.model.get('delta') + '-' + widget.model.get('level');
      widget.$el.prop('id', elemId)
              .prop('disabled', widget.container.app.$el.prop('disabled'))
              .attr('aria-labelledby', elemId + '-label')
              .addClass('shs-select')
              // Add core classes to apply default styles to the element.
              .addClass('form-select')
              .addClass('form-element')
              .addClass('form-element--type-select')
              .hide();
      if (widget.$el.attr('style') && (widget.$el.attr('style') === '')) {
        widget.$el.removeAttr('style');
      }
      if (widget.model.get('dataLoaded')) {
        widget.$el.show();
      }
      if (widget.container.app.getSetting('required')) {
        widget.$el.addClass('required');
        // Add HTML5 required attributes to first level.
        if (widget.model.get('level') === 0) {
          widget.$el.attr('required', 'required');
          widget.$el.attr('aria-required', 'true');

          // Remove attributes from original element!
          widget.container.app.$el.removeAttr('required');
          widget.container.app.$el.removeAttr('aria-required');
        }
      }
      if (widget.container.app.hasError()) {
        widget.$el.addClass('error');
      }

      // Remove all existing options.
      $('option', widget.$el).remove();

      var defaultValue = widget.model.get('defaultValue');
      var defaultValueExistsOnOptions = false;

      // Create options from collection.
      widget.model.itemCollection.each(function (item) {
        if (!item.get('tid')) {
          return;
        }

        // We know that default value exists on options.
        if (defaultValue == item.get('tid')) {
          defaultValueExistsOnOptions = true;
        }

        var optionModel = new Drupal.shs.classes[widget.container.app.getConfig('fieldName')].models.widgetItemOption({
          label: item.get('name'),
          value: item.get('tid'),
          hasChildren: item.get('hasChildren')
        });
        var option = new Drupal.shs.classes[widget.container.app.getConfig('fieldName')].views.widgetItem({
          model: optionModel
        });
        widget.$el.append(option.render().$el);
      });

      // Add "any" option.
      if (widget.model.itemCollection.length > 1) {
        widget.$el.append($('<option>').text(widget.container.app.getSetting('anyLabel')).val(widget.container.app.getSetting('anyValue')));
      }

      var $container = $('.shs-widget-container[data-shs-level="' + widget.model.get('level') + '"]', widget.container.$el);
      if (widget.model.itemCollection.length === 0 && !widget.container.app.getSetting('create_new_levels')) {
        // Do not create the widget.
        $container.remove();
        return widget;
      }

      // Create label if necessary.
      if ((widget.container.model.get('delta') === 0) || widget.container.app.getConfig('display.labelsOnEveryLevel')) {
        var labels = widget.container.app.getConfig('labels') || [];
        var label = widget.container.app.getConfig('bundleLabel');
        // Use value of parent on level > 0.
        if (widget.model.get('level') > 0) {
          var parentModel = widget.container.collection.models[widget.model.get('level') - 1];
          var parentValue = parentModel.get('defaultValue');
          var parentItem = parentModel.findItemModel(parentValue);
          if (parentItem) {
            label = parentItem.get('name');
          }
        }
        // Allow custom label overrides.
        if (labels.hasOwnProperty(widget.model.get('level')) && (labels[widget.model.get('level')] !== false)) {
          label = labels[widget.model.get('level')];
        }
        $('<label>')
                .prop('id', widget.$el.prop('id') + '-label')
                .addClass('visually-hidden')
                .text(label)
                .appendTo($container);
        widget.$el.prop('aria-labelled-by', widget.$el.prop('id') + '-label');
      }

      // If the default value is not in the options list and not equals to
      // "any" value, we force it to be "any" value. Otherwise the list displays
      // an empty value.
      if (!defaultValueExistsOnOptions && (defaultValue !== widget.container.app.getSetting('anyValue'))) {
        defaultValue = widget.container.app.getSetting('anyValue');
      }

      widget.$el.val(defaultValue);

      // Add widget to container.
      if (widget.model.get('dataLoaded')) {
        // Add element without using any effect.
        $container.append(widget.$el);
      }
      else {
        $container.append(widget.$el.fadeIn(widget.container.app.getConfig('display.animationSpeed')));
      }

      widget.model.set('dataLoaded', true);
      // Return self for chaining.
      return widget;
    },
    /**
     * React to selection changes within the element.
     */
    selectionChange: function () {
      var value = $(this.el).val();
      // Update default value of attached model.
      this.model.set('defaultValue', value);
      // Fire events.
      this.container.collection.trigger('update:selection', this.model, value, this);
    }

  });

  /**
   * @constructor
   *
   * @augments Backbone.Collection
   */
  Drupal.shs.WidgetItemCollection = Backbone.Collection.extend(/** @lends Drupal.shs.WidgetItemCollection */{
    /**
     * @type {Drupal.shs.WidgetItemModel}
     */
    model: Drupal.shs.WidgetItemModel,
    /**
     * {@inheritdoc}
     */
    initialize: function (options) {
      this.url = options.url;
    }
  });

}(jQuery, Drupal, drupalSettings, Backbone));
