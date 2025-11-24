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
      This.sumEntries();
      $('.kernel-entry').each(function () {
        var $matrix_wrapper = $(this);
        var $matrixInputs = $matrix_wrapper.find('input');
        $matrixInputs.change(function () {
          This.sumEntries();
        });
      });
    },

    sumEntries: function () {
      var out = 0;
      var entries = $('.kernel-entry').find('input');
      for (var i = 0; i < entries.length; i++) {
        var f = parseFloat($(entries[i]).val());
        out += f ? f : 0;
      }
      $('.kernel-matrix-sum').html(out);
      return out;
    }

  };
})(jQuery);
