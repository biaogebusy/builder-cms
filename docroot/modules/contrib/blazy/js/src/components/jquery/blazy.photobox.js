/**
 * @file
 * Provides Photobox integration for Image and Media fields.
 *
 * @tbd deprecated at 2.5 and and removed at 3.+, this library is unmaintained,
 * and has good replacements like PhotoSwipe, Splidebox, Slick Lightbox, etc.
 */

(function ($, Drupal, _doc) {

  'use strict';

  var _id = 'photobox';
  var _nick = 'pbox';
  var _idOnce = 'b-' + _nick;
  var _mounted = 'is-' + _idOnce;
  var _element = '[data-' + _id + '-gallery]:not(.' + _mounted + ')';

  /**
   * Blazy Photobox utility functions.
   *
   * @param {HTMLElement} box
   *   The photobox HTML element.
   */
  function process(box) {
    var $box = $(box);

    function callback(el) {
      if ($.isElm(el)) {
        var caption = $.next(el);
        if ($.isElm(caption)) {
          var title = $.find(_doc, '#pbCaption .title');
          if ($.isElm(title)) {
            title.innerHTML = $.sanitizer.sanitize(caption.innerHTML);
          }
        }
      }
    }

    $box.photobox('a[data-photobox-trigger]', {
      thumb: '> [data-thumb]',
      thumbAttr: 'data-thumb'
    }, callback);

    $.addClass(box, _mounted);
  }

  /**
   * Attaches blazy photobox behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyPhotobox = {
    attach: function (context) {

      // Converts jQuery.photobox into dBlazy.photobox to demonstrate the new
      // dBlazy plugin system post Blazy 2.6.
      if (jQuery && $.isFun(jQuery.fn.photobox) && !$.isFun($.fn.photobox)) {
        var _pb = jQuery.fn.photobox;

        $.fn.photobox = function (target, settings, callback) {
          return $(_pb.apply(this, arguments));
        };
      }

      $.once(process, _idOnce, _element, context);

    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(_idOnce, _element, context);
      }
    }
  };

}(dBlazy, Drupal, this.document));
