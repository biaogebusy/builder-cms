/**
 * @file
 * Contains .
 */

(function ($, _, Backbone, Drupal) {
  'use strict';

  Drupal.panels_ipe_builder.BlockPicker = Drupal.panels_ipe.BlockPicker.extend({

    activeBlockTypeCategory: 1,

    /**
     * @type {function}
     */
    template_content_type_category: _.template(
      '  <a href="javascript:;" class="ipe-block-type-category<% if (active) { %> active<% } %>" data-block-type-category="<%- id %>">' +
        '    <div class="ipe-block-type-category-info">' +
        '<%- label %>' +
      '    </div>' +
        '  </a>'
    ),

    /**
     * @type {object}
     */
    events: {
      'click a[data-block-type-category]': 'toggleBlockTypeCategory'
    },

    initialize: function (options) {
      var self = this;
      _.extend(this.events, Drupal.panels_ipe.BlockPicker.prototype.events);

    },

    collectionFilter: false,

    max: 16,

    resultCollection: function(_collection){

      var self = this;
      var results = _.filter(_collection.models, function(item, index){ return index < self.max });
      var collection = new Drupal.panels_ipe.BlockPluginCollection();
      collection.set(results);
      return collection;

    },

    render: function () {
      var self = this;

      Drupal.panels_ipe.BlockPicker.prototype.render.apply(this);

      if(drupalSettings.panels_ipe.panels_display.storage_id.match('alert_template')){
        this.$('.ipe-category[data-category="Shared Content"]').hide();
        return;
      }

      if (this.activeCategory === Drupal.t('Shared Content')) {

        this.orgCollection = this.orgCollection ? this.orgCollection : this.collection.clone();

        if(!self.collectionFilter){

          self.collection = this.resultCollection(this.orgCollection);
          self.collectionFilter = true;
          self.render();

        }

        // Dom Set @wenroo

        var $div = $('<div/>',{
          class:'form-item form-item-share-content-search clearfix'
        });
        var $label = $('<label/>',{
          text: 'Filter',
        }).appendTo($div);
        var $search = $('<input/>',{
          type: "text",
          value: self._val || '',
          class: "form-text filter-form-input",
          placeholder: "Widget Keyword"
        }).appendTo($div);

        this.$('.ipe-category-picker-top').once().after($div);

        var valLength = self._val && self._val.length || 0;
        $search[0].focus();
        $search[0].setSelectionRange(valLength, valLength);

        // Math Data @wenroo @todo count < 16
        var time = 400;
        var timeout,
            searching = function(el){
              if (!timeout) return;
              timeout = null;
              if(self._val.length > 0){
                var indexval = self._val.toLowerCase();
                var results = _.filter(self.orgCollection.models, function(model, index){
                  var label = model.get('label') && model.get('label').toLowerCase();
                  return label && label.indexOf(indexval) !== -1;
                });

                var collection = new Drupal.panels_ipe.BlockPluginCollection();
                collection.set(_.filter(results, function(item, index){
                  return index < self.max
                }));
                self.collection = collection;

                self.render();

              }else{

                self.collection = self.resultCollection(self.orgCollection);
                self.render();

              }
            };

        $search.bind('keyup', function(event) {
          self._val = $(this).val();
          if (timeout){
            clearTimeout(timeout);
          }
          timeout = setTimeout(function(){
            searching();
          }, time);
        });

      }

      if (this.activeCategory === Drupal.t('Create Content')) {
        this.$('.ipe-category-picker-top').html('');
        this.$('.ipe-category-picker-top').append('<div class="ipe-block-type-categories-wrapper"><div class="ipe-block-type-categories">');
        // /admin/structure/eck/entity/block_type_attribute/types/manage/block_type_attribute/fields/block_type_attribute.block_type_attribute.category/storage
        var block_type_categories = new Backbone.Collection([
          {id: 1, label: 'Hero'},
          {id: 2, label: 'Copy'},
          {id: 3, label: 'Card'},
          {id: 4, label: 'Action'},
          {id: 6, label: 'Photo'},
          {id: 7, label: 'Showcase'},
          {id: 8, label: 'Promo'},
          {id: 10, label: 'Navigation'},
          {id: 11, label: 'Video'},
          {id: 12, label: 'Carousel'},
          {id: 98, label: 'Branding'},
          {id: 99, label: 'Other'},
          {id: 0, label: 'Deprecated'}
        ]);
        if(drupalSettings.xinshi_editor_panelizer.groups != 'undefined') {
          block_type_categories.reset();
          _.each(drupalSettings.xinshi_editor_panelizer.groups, function(group) {
            var block_type = self.contentCollection.where({category:group.id});
            if(block_type.length > 0)
              block_type_categories.add({id:group.id, label:group.name});
          });
        }
        block_type_categories.each(function (block_type_category) {
          var template_vars = block_type_category.toJSON();
          if (this.activeBlockTypeCategory === template_vars.id) {
            template_vars.active = 'active';
          }
          else {
            template_vars.active = '';
          }
          this.$('.ipe-block-type-categories').append(this.template_content_type_category(template_vars));
        }, this);
        this.contentCollection.comparator = 'weight';
        this.contentCollection.each(function (block_content_type) {
          if (this.activeBlockTypeCategory == block_content_type.get('category')) {
            var template_vars = block_content_type.toJSON();
            template_vars.trimmed_description = template_vars.description;
            if (template_vars.trimmed_description.length > 30) {
              template_vars.trimmed_description = template_vars.description.substring(0, 30) + '...';
            }

            this.$('.ipe-category-picker-top').append(this.template_content_type(template_vars));
            this.$('.ipe-blockpicker-item a[data-block-type="' + template_vars.id + '"]').tooltip(
              // {show: {effect: "slideDown",delay: 250}},
              // {hide: {effect: "explode",delay: 250}},
              {position: {my: 'bottom', at: 'top-30'}},
              {content: template_vars.images.map(function (src) {
                return '<div class="panel-tooltip"><div class="panel-tooltip-inner"><img src="' + src + '"/><div class="description">' + template_vars.description + '</div></div></div>';
              }).join()}
            );
          }

        }, this);
        this.$('.ipe-category-picker-top').append('</div></div>');
      }
      // Create Content is default
      if(this.$el.find('.ipe-category-picker-top').html() == '' && !this.activeCategory) {
         this.$el.find('[data-category]').eq(0).trigger('click');
         setTimeout(function() {
          self.$el.find('.ipe-category-picker-top.active a.ipe-block-type-category').eq(0).trigger('click');
         }, 500);
      }
    },

    toggleBlockTypeCategory: function (e) {
      this.activeBlockTypeCategory = $(e.currentTarget).data('block-type-category');
      this.render();
    },

    displayForm: function (e) {
      var info = this.getFormInfo(e);
      var plugin_id = $(e.currentTarget).data('plugin-id');
      if (plugin_id && this.activeCategory == Drupal.t('Custom')) {
        this.activeCategory = null;
      }

      this.loadForm(info);
    }

  });

}(jQuery, _, Backbone, Drupal));
