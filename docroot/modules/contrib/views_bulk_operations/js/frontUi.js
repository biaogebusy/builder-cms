/**
 * @file
 * Select-All Button functionality.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.views_bulk_operations = {
    attach: function (context, settings) {
      $('.vbo-view-form').once('vbo-init').each(Drupal.viewsBulkOperationsFrontUi);
    }
  };

  /**
   * Views Bulk Operation selection object.
   */
  Drupal.viewsBulkOperationsSelection = {
    view_id: '',
    display_id: '',
    list: {},
    $placeholder: null,

    /**
     * Bind event handlers to an element.
     *
     * @param {jQuery} element
     */
    bindEventHandlers: function ($element, index) {
      if ($element.length) {
        var selectionObject = this;
        $element.on('keypress', function (event) {
          // Emulate click action for enter key.
          if (event.which === 13) {
            event.preventDefault();
            event.stopPropagation();
            selectionObject.update(!this.checked, index, $(this).val());
            $(this).trigger('click');
          }
          if (event.which === 32) {
            selectionObject.update(!this.checked, index, $(this).val());
          }
        });
        $element.on('click', function (event) {
          // Act only on left button click.
          if (event.which === 1) {
            selectionObject.update(this.checked, index, $(this).val());
          }
        });
      }
    },

    /**
     * Perform an AJAX request to update selection.
     *
     * @param {bool} state
     * @param {mixed} index
     * @param {string} value
     */
    update: function (state, index, value) {
      if (value === undefined) {
        value = null;
      }
      if (this.view_id.length && this.display_id.length) {
        // TODO: prevent form submission when ajaxing.

        var list = {}, op = '';
        if (index === 'selection_method_change') {
          var op = state ? 'method_exclude' : 'method_include';
          if (state) {
            list = this.list[index];
          }
        }
        else {
          if (value && value != 'on') {
            list[value] = this.list[index][value];
          }
          else {
            list = this.list[index];
          }
          op = state ? 'add' : 'remove';
        }

        var $placeholder = this.$placeholder;
        var $selectionInfo = this.$selectionInfo;
        var target_uri = drupalSettings.path.baseUrl + drupalSettings.path.pathPrefix + 'views-bulk-operations/ajax/' + this.view_id + '/' + this.display_id;

        $.ajax(target_uri, {
          method: 'POST',
          data: {
            list: list,
            op: op
          },
          success: function (data) {
            $selectionInfo.html($(data.selection_info).html());
            $placeholder.text(data.count);
          }
        });
      }
    }
  }

  /**
   * Callback used in {@link Drupal.behaviors.views_bulk_operations}.
   */
  Drupal.viewsBulkOperationsFrontUi = function () {
    var $vboForm = $(this);
    var $viewsTables = $('.vbo-table', $vboForm);
    var $primarySelectAll = $('.vbo-select-all', $vboForm);
    var tableSelectAll = [];

    // When grouping is enabled, there can be multiple tables.
    if ($viewsTables.length) {
      $viewsTables.each(function (index) {
        tableSelectAll[index] = $(this).find('.select-all input').first();
      });
      var $tableSelectAll = $(tableSelectAll);
    }

    // Add AJAX functionality to row selector checkboxes.
    var $multiSelectElement = $vboForm.find('.vbo-multipage-selector').first();
    if ($multiSelectElement.length) {

      Drupal.viewsBulkOperationsSelection.$selectionInfo = $multiSelectElement.find('.vbo-info-list-wrapper').first();
      Drupal.viewsBulkOperationsSelection.$placeholder = $multiSelectElement.find('.placeholder').first();
      Drupal.viewsBulkOperationsSelection.view_id = $multiSelectElement.attr('data-view-id');
      Drupal.viewsBulkOperationsSelection.display_id = $multiSelectElement.attr('data-display-id');
      Drupal.viewsBulkOperationsSelection.vbo_form = $vboForm;

      // Get the list of all checkbox values and add AJAX callback.
      Drupal.viewsBulkOperationsSelection.list = [];

      var $contentWrappers;
      if ($viewsTables.length) {
        $contentWrappers = $viewsTables;
      }
      else {
        $contentWrappers = $([$vboForm]);
      }

      $contentWrappers.each(function (index) {
        var $contentWrapper = $(this);
        Drupal.viewsBulkOperationsSelection.list[index] = {};

        $contentWrapper.find('.views-field-views-bulk-operations-bulk-form input[type="checkbox"]').each(function () {
          var value = $(this).val();
          if (value != 'on') {
            Drupal.viewsBulkOperationsSelection.list[index][value] = value;
            Drupal.viewsBulkOperationsSelection.bindEventHandlers($(this), index);
          }
        });

        // Bind event handlers to select all checkbox.
        if ($viewsTables.length && tableSelectAll.length) {
          Drupal.viewsBulkOperationsSelection.bindEventHandlers(tableSelectAll[index], index);
        }
      });
    }

    // Initialize all selector if the primary select all and
    // view table elements exist.
    if ($primarySelectAll.length) {
      $primarySelectAll.on('change', function (event) {
        var value = this.checked;

        // Select / deselect all checkboxes in the view.
        // If there are table select all elements, use that.
        if (tableSelectAll.length) {
          tableSelectAll.forEach(function (element) {
            if (element.get(0).checked != value) {
              element.click();
            }
          });
        }

        // Also handle checkboxes that may still have different values.
        $vboForm.find('.views-field-views-bulk-operations-bulk-form input[type="checkbox"]').each(function () {
          if (this.checked != value) {
            $(this).click();
          }
        });

        // Clear the selection information if exists.
        $vboForm.find('.vbo-info-list-wrapper').each(function () {
          $(this).html('');
        });
      });

      if ($multiSelectElement.length) {
        Drupal.viewsBulkOperationsSelection.bindEventHandlers($primarySelectAll, 'selection_method_change');
      }
    }
  };

})(jQuery, Drupal);
