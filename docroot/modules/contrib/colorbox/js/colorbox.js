/**
 * @file
 * Colorbox JS.
 */

(function ($, Drupal, drupalSettings, once) {

  'use strict';

  Drupal.behaviors.initColorbox = {
    attach: function (context, settings) {
      if (typeof $.colorbox !== 'function' || typeof settings.colorbox === 'undefined') {
        return;
      }

      // The colorbox library uses jQuery.isFunction().
      // This function was removed in jQuery 3.3.0.
      // This is a workaround to avoid fixing the library.
      if (!$.isFunction) {
        $.isFunction = function (obj) {
          return typeof obj === 'function' || false;
        };
      }

      if (settings.colorbox.mobiledetect && window.matchMedia) {
        // Disable Colorbox for small screens.
        var mq = window.matchMedia('(max-device-width: ' + settings.colorbox.mobiledevicewidth + ')');
        if (mq.matches) {
          $.colorbox.remove();
          return;
        }
      }

      settings.colorbox.rel = function () {
        return $(this).data('colorbox-gallery');
      };

      settings.colorbox.html = function () {
        var $modalContent = $(this).find('> .modal-content');
        return $modalContent.length ? $(this).find('> .modal-content').children().clone() : false;
      };

      $(once('init-colorbox', '.colorbox', context))
        .each(function() {
        // Only images are supported for the "colorbox" class.
        // The "photo" setting forces the href attribute to be treated as an image.
        var extendParams = {
          photo: true
        };
        // If a title attribute is supplied, sanitize it.
        var title = $(this).attr('title');
        if (typeof title === 'undefined') {
          title = this.dataset.cboxTitle;
        }
        if (title) {
          extendParams.title = Drupal.colorbox.sanitizeMarkup(title);
        }
        $(this).colorbox($.extend({}, settings.colorbox, extendParams));

        // Only allow http or https protocol in hrefs.
        var href = $(this).attr('href');
        var protocolRegex = /^(https?)/;
        if (href && href.substring(0, 1) !== '/') {
          var protocol = href.split(':')[0];
          // Use a regex to match http or https protocol.
          if (!protocolRegex.test(protocol)) {
            $(this).removeAttr('href');
          }
        }
        var dataHref = this.dataset.cboxHref;
        if (dataHref && dataHref.substring(0, 1) !== '/') {
          var dataProtocol = dataHref.split(':')[0];
          if (!protocolRegex.test(dataProtocol)) {
            delete this.dataset.cboxHref;
          }
        }

        // Since the sanitized title has been passed to colorbox settings,
        // delete the unsanitized data-cbox-title attribute.
        delete this.dataset.cboxTitle;

        // Disallow dangerous data attributes.
        delete this.dataset.cboxIframeAttrs;

        // Sanitize other data attributes.
        var sanitizeDataList = ['cboxNext', 'cboxPrevious', 'cboxCurrent',
          'cboxClose', 'cboxSlideshowstop', 'cboxSlideshowstart',
          'cboxXhrError', 'cboxImgerror', 'cboxHtml'
        ];
        for (var a of sanitizeDataList) {
          if (this.dataset.hasOwnProperty(a)) {
            this.dataset[a] = Drupal.colorbox.sanitizeMarkup(this.dataset[a]);
          }
        }
      });

      $('.colorbox', context).colorbox({
        onComplete: function (e) {
          var focus = $('#cboxContent').find('#cboxPrevious').css('display') !== 'none' ? $('#cboxContent').find('#cboxPrevious') : $('#cboxContent').find('#cboxClose');
          focus.focus();

          $('#cboxContent').on('keydown', function (e) {
            var keyCode = e.keyCode || e.which;
            var firstElement = $('#cboxContent').find('#cboxPrevious').last().is(':focus');
            var lastElement = $('#cboxContent').find('#cboxClose').first().is(':focus');
            if (keyCode === 9 && !e.shiftKey && lastElement) {
              e.preventDefault();
              $('#cboxContent').find('#cboxPrevious').first().focus();
            }
            else if (keyCode === 9 && e.shiftKey && firstElement) {
              e.preventDefault();
              $('#cboxContent').find('#cboxClose').first().focus();
            }
          });
        }
      });
    }
  };

  // Create colorbox namespace if it doesn't exist.
  if (!Drupal.hasOwnProperty('colorbox')) {
    Drupal.colorbox = {};
  }

  /**
   * Global function to allow sanitizing captions and control strings.
   *
   * @param markup
   *   String containing potential markup.
   * @return @string
   *  Sanitized string with potentially dangerous markup removed.
   */
  Drupal.colorbox.sanitizeMarkup = function(markup) {
    // If DOMPurify installed, allow some HTML. Otherwise, treat as plain text.
    if (typeof DOMPurify !== 'undefined') {
      var purifyConfig = {
        ALLOWED_TAGS: [
          'a',
          'b',
          'strong',
          'i',
          'em',
          'u',
          'cite',
          'code',
          'br'
        ],
        ALLOWED_ATTR: [
          'href',
          'hreflang',
          'title',
          'target'
        ]
      }
      if (drupalSettings.hasOwnProperty('dompurify_custom_config')) {
        purifyConfig = drupalSettings.dompurify_custom_config;
      }
      return DOMPurify.sanitize(markup, purifyConfig);
    }
    else {
      return Drupal.checkPlain(markup);
    }
  }

})(jQuery, Drupal, drupalSettings, once);
