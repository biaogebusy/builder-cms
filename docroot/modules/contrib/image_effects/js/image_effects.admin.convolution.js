/**
 * @file
 *
 * Image effects admin ui.
 */

(function ($) {
  'use strict';
  Drupal.behaviors.imageEffectsAdminConvolution = {
    attach: function (context, settings) {
      var This = this;
      $('.form-item-data-kernel').each(function () {
        var $matrix_wrapper = $(this);
        var $matrixInputs = $matrix_wrapper.find('input');
        This.sumEntrie($matrixInputs, $matrix_wrapper);
        $matrixInputs.change(function () {
          This.sumEntrie($matrixInputs, $matrix_wrapper);
        });
      });
    },
    sumEntrie: function (entries, context) {
      var out = 0;
      $.each(entries, function (index, entry) {
        var f = parseFloat($(entry).val());
        out += f ? f : 0;
      });
      $('.kernel-matrix-sum', context).html(out);
      return out;
    }
  };
})(jQuery);
