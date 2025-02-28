/**
 * @file
 * Attaches behavior for the Multiselect module.
 */
(function ($, Drupal, drupalSettings) {
  "use strict";

  /**
   * Provide the summary information for the block settings vertical tabs.
   */
  Drupal.behaviors.multiSelect = {
    attach: function (context) {
      var $submit = $(context).find('input.form-submit').once('multiselect');
      var $multiselect = $(context).find('select.multiselect-available').once('multiselect');
      var $multiselectAvailable = $(context).find('select.multiselect-available').once('multiselectAvailable');
      var $multiselectSelected = $(context).find('select.multiselect-selected').once('multiselectSelected');
      var $multiselectAdd = $(context).find('li.multiselect-add').once('multiselectAdd');
      var $multiselectRemove = $(context).find('li.multiselect-remove').once('multiselectRemove');

      // Note: Doesn't matter what sort of submit button it is really (preview or submit).
      // Selects all the items in the selected box (so they are actually selected) when submitted.
      $submit.on('click mousedown', function () {
        $('select.multiselect-selected').selectAll();
      });

      // Remove the items that haven't been selected from the select box.
      $multiselect.each(function () {
        var $parent = $(this).parents('.multiselect-wrapper');
        var $available = $('div.multiselect-available select', $parent);
        var $selected = $('div.multiselect-selected select', $parent);
        $available.removeContentsFrom($selected);
      });

      // Moves selection if it's double clicked to selected box.
      $multiselectAvailable.on('dblclick', function () {
        var $parent = $(this).parents('.multiselect-wrapper');
        var $available = $('div.multiselect-available select', $parent);
        var $selected = $('div.multiselect-selected select', $parent);
        $available.moveSelectionTo($selected);
      });

      // Moves selection if it's double clicked to unselected box.
      $multiselectSelected.on('dblclick', function () {
        var $parent = $(this).parents('.multiselect-wrapper');
        var $available = $('div.multiselect-available select', $parent);
        var $selected = $('div.multiselect-selected select', $parent);
        $selected.moveSelectionTo($available);
      });

      // Moves selection if add is clicked to selected box.
      $multiselectAdd.on('click', function (e) {
        e.preventDefault();
        var $parent = $(this).parents('.multiselect-wrapper');
        var $available = $('div.multiselect-available select', $parent);
        var $selected = $('div.multiselect-selected select', $parent);
        $available.moveSelectionTo($selected);
      });

      // Moves selection if remove is clicked to selected box.
      $multiselectRemove.on('click', function (e) {
        e.preventDefault();
        var $parent = $(this).parents('.multiselect-wrapper');
        var $available = $('div.multiselect-available select', $parent);
        var $selected = $('div.multiselect-selected select', $parent);
        $selected.moveSelectionTo($available);
      });

      if (drupalSettings.multiselect.widths) {
        var widths = drupalSettings.multiselect.widths;
        $(context).find("div.multiselect-available, div.multiselect-selected, select.form-multiselect").width(widths);
      }
    }
  };
})(jQuery, Drupal, drupalSettings);

/**
 * Selects all the items in the select box it is called from.
 * Usage $('nameofselectbox').selectAll();
 */
jQuery.fn.selectAll = function () {
  this.each(function () {
    for (var x = 0; x < this.options.length; x++) {
      var option = this.options[x];
      option.selected = true;
    }
  });
}

/**
 * Removes the content of this select box from the target.
 * Usage $('nameofselectbox').removeContentsFrom(target_selectbox);
 */
jQuery.fn.removeContentsFrom = function () {
  var dest = arguments[0];
  this.each(function () {
    for (var x = this.options.length - 1; x >= 0; x--) {
      dest.removeOption(this.options[x].value);
    }
  });
}

/**
 + * Moves the selection to the select box specified.
 + * Usage $('nameofselectbox').moveSelectionTo(destination_selectbox);
 + */
jQuery.fn.moveSelectionTo = function () {
  var dest = arguments[0];
  this.each(function () {
    for (var x = 0; x < this.options.length; x++) {
      var option = this.options[x];
      if (option.selected) {
        dest.addOption(option);
        this.remove(x);
        x--;
      }
    }
  });
}

/**
 + * Adds an option to a select box.
 + * Usage $('nameofselectbox').addOption(optiontoadd);
 + */
jQuery.fn.addOption = function () {
  var option = arguments[0];
  this.each(function () {
    // Had to alter code to this to make it work in IE.
    var anOption = document.createElement('option');
    anOption.text = option.text;
    anOption.value = option.value;
    this.options[this.options.length] = anOption;
    // $(this).triggerHandler('option-added', anOption);
    return false;
  });
}

/**
 + * Removes an option from a select box.
 + * usage $('nameofselectbox').removeOption(valueOfOptionToRemove);
 + */
jQuery.fn.removeOption = function () {
  var targOption = arguments[0];
  this.each(function () {
    for (var x = this.options.length - 1; x >= 0; x--) {
      var option = this.options[x];
      if (option.value == targOption) {
        this.remove(x);
        //$(this).triggerHandler('option-removed', option);
      }
    }
  });
}
