/**
 * @file
 * Contains a workaround for drupal core issue #1149078.
 */

(function ($) {
  'use strict';
  function select_or_other_check_and_show($select, speed) {
    var $select_id = $select.attr('id').replace('select', 'other').replace('edit-field-', '')
    var $other = $(`.js-form-item-field-${$select_id}`)
    if ($select.find("option:selected[value=select_or_other]").length) {
      $other.show(speed, function () {
        if ($(this).hasClass('select-or-other-initialized')) {
          $(this).find("input").focus();
        }
      });
    }
    else {
      $other.hide(speed);
      if ($(this).hasClass('select-or-other-initialized')) {
        // Special case, when the page is loaded, also apply 'display: none' in case it is
        // nested inside an element also hidden by jquery - such as a collapsed fieldset.
        $other.css("display", "none");
      }
    }
  }

  /**
   * The Drupal behaviors for the Select (or other) field.
   */
  Drupal.behaviors.select_or_other = {
    attach: function (context) {
      $(".js-form-type-select-or-other-select", context).once().each(function () {
        var $select = $('select', this);
        // Hide the other field if applicable.
        select_or_other_check_and_show($select, 0);
        $select.addClass('select-or-other-initialized');

        // Bind event callbacks.
        $select.change(function () {
          select_or_other_check_and_show($(this), 200);
        });
        $select.click(function () {
          select_or_other_check_and_show($(this), 200);
        });
      });
    }
  };

})(jQuery);
