/**
 * @file
 * Contains a workaround for drupal core issue #1149078.
 */

(function (Drupal, $, once) {
  function selectOrOtherCheckAndShow($select, speed) {
    const $selectId = $select
      .attr('id')
      .replace('select', 'other')
      .replace('edit-field-', '');
    const $other = $(`.js-form-item-field-${$selectId}`);
    if ($select.find('option:selected[value=select_or_other]').length) {
      $other.show(speed, function () {
        if ($(this).hasClass('select-or-other-initialized')) {
          $(this).find('input').focus();
        }
      });
    } else {
      $other.hide(speed);
      if ($(this).hasClass('select-or-other-initialized')) {
        // Special case, when the page is loaded, also apply 'display: none' in case it is
        // nested inside an element also hidden by jquery - such as a collapsed fieldset.
        $other[0].style.display = 'none';
      }
    }
  }

  /**
   * The Drupal behaviors for the Select (or other) field.
   */
  Drupal.behaviors.select_or_other = {
    attach(context) {
      $(once('select-or-other', '.js-form-type-select-or-other-select')).each(
        function () {
          const $select = $('select', this);
          // Hide the other field if applicable.
          selectOrOtherCheckAndShow($select, 0);
          $select.addClass('select-or-other-initialized');

          // Bind event callbacks.
          $select.change(function () {
            selectOrOtherCheckAndShow($(this), 200);
          });
          $select.click(function () {
            selectOrOtherCheckAndShow($(this), 200);
          });
        },
      );
    },
  };
})(Drupal, jQuery, once);
