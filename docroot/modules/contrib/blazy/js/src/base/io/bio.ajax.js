/**
 * @file
 * Provides Intersection Observer API AJAX helper.
 *
 * Blazy IO works fine with AJAX, until using VIS, or alike. Adds a helper.
 * Required to fix for what Native lazy doesn't support Blur, Video, BG.
 * Similar to core responsive_image/ajax fix, only different approach.
 *
 * @todo remove once bio.js plays nice for media, VIS, blocks.
 */

(function ($, jq, Drupal, drupalSettings, _doc) {

  'use strict';

  var VARS = {
    id: 'b-ajax',
    selector: 'body',
    eventName: 'ajaxSuccess',
    revRAF: null
  };

  /**
   * Process DOM revalidations with newly added AJAX contents.
   */
  function process() {
    var me = this;

    var revalidate = function (_, response, ajax) {

      if (!$.wwoBigPipeDone() || !response) {
        return;
      }

      // Clear any pending timer.
      if (VARS.revRAF) {
        cancelAnimationFrame(VARS.revRAF);
        VARS.revRAF = null;
      }

      // DOM ready fix.
      VARS.revRAF = requestAnimationFrame(function () {
        Promise.resolve().then(function () {
          var bio = me.init;

          // 1. Ensure we have Bio loaded.
          if (!bio) {
            return;
          }

          var opts = me.options;
          var el = $.find(_doc, $.selector(opts, true));

          // See blazy.load.js.
          // 2. Ensure we have lazy elements after AJAX.
          if (el) {
            var context = _doc.body;
            var prev = $.once.unload;
            $.once.unload = true;

            $.once.remove('b-root', 'body', _doc);

            Drupal.attachBehaviors(context, drupalSettings);

            $.trigger('blazy:ajaxSuccess', [me, response, ajax]);

            // Reset flag.
            $.once.unload = prev;
          }

        });
      });

    };

    // jQuery owned document, cannot use dBlazy. Keep it alive.
    jq(_doc).on(VARS.eventName, revalidate);
  }

  /**
   * Attaches blazy AJAX behavior to body.
   *
   * Seperated from blazy.load.js, since blazy.load.js can be disabled, and
   * removed to use blazy.compat.js instead that is when No Javascript option is
   * enabled, but JS is still required beyond iframe or img tags such as by
   * background, local video or audio, or third party HTML lazyloadings.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyAjax = {
    attach: function () {

      var me = Drupal.blazy;

      $.once(process.bind(me), VARS.id, VARS.selector, _doc);

    },
    detach: function (context, _, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(VARS.id, VARS.selector, _doc);
      }
    }
  };

})(dBlazy, jQuery, Drupal, drupalSettings, this.document);
