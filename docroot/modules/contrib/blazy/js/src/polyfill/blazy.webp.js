/**
 * @file
 * Provides a few disposable polyfills till IE is gone from planet earth.
 *
 * Supports for webp is landed at D9.2. This file relies on core/picturefill
 * which is always included as core/responsive_image polifyll as per 2022/2.
 * This file is a client-side solution, with advantage clean native image markup
 * since it doesn't change IMG into PICTURE till required by old browsers, as
 * alternative for HTML/ server-side solutions:
 *   - https://www.drupal.org/project/webp
 *   - https://www.drupal.org/project/imageapi_optimize_webp
 *
 * @see https://www.drupal.org/node/3171135
 * @see https://www.drupal.org/project/drupal/issues/3213491
 * @todo remove if picturefill suffices. FWIW, IE9 works fine with picturefill
 * w/o this fallback. Not tested against other oldies, Safari, etc. So included,
 * but can be ditched as usual via Blazy UI if not needed at all.
 */

(function ($, _win, _doc) {

  'use strict';

  var KEY_STORAGE = 'bwebp';
  var DATA_SRCSET = 'data-srcset';
  var PICTURE = 'picture';
  var MIME_WEBP = 'image/webp';
  var SOURCE = 'source';
  var FN_PF = _win.picturefill;
  var SRCSET_CACHE = {};

  function isSupported() {
    var support = true;

    // Ensures not locked down when Responsive image is not present, yet.
    // @todo use $.decode for better async.
    if (FN_PF) {
      var check = $.storage(KEY_STORAGE);

      if (!$.isNull(check)) {
        return check === 'true';
      }

      // Undefined means supported, due to !FN_PF.supPicture check.
      support = $.isUnd(FN_PF._.supportsType(MIME_WEBP));
      $.storage(KEY_STORAGE, support);
    }

    return support;
  }

  // Check final .webp extension
  function isFinalWebp(url) {
    return /\.webp(\?|#|$)/i.test((url || '').trim().split(/\s+/)[0]);
  }

  function parseSrcsetCached(img) {
    var id = $.attr(img, 'id');
    if (!id) {
      id = 'bwebp-' + Math.random().toString(36).slice(2); $.attr(img, 'id', id);
    }

    if (SRCSET_CACHE[id]) {
      return SRCSET_CACHE[id];
    }

    var dataset = $.attr(img, DATA_SRCSET);
    var srcset = $.attr(img, 'srcset');
    srcset = srcset && srcset.length ? srcset : dataset;

    var webps = [];
    var nowebps = [];

    if (srcset && srcset.length) {
      var candidates = srcset.split(',');

      $.each(candidates, function (src) {
        src = src.trim();
        var url = src.split(/\s+/)[0];

        if (isFinalWebp(url)) {
          webps.push(src);
        }
        else {
          nowebps.push(src);
        }
      });
    }

    var result = {
      webps: webps,
      nowebps: nowebps
    };

    SRCSET_CACHE[id] = result;
    return result;
  }

  // Clear cache for single element or all.
  function clearCache(img) {
    if (img) {
      var id = $.attr(img, 'id');

      if (id && SRCSET_CACHE[id]) {
        delete SRCSET_CACHE[id];
      }
    }
    else {
      SRCSET_CACHE = {};
    }
  }

  // Convert <img> -> <picture>.
  function convert(el, refresh) {
    if (!$.isElm(el)) {
      return false;
    }

    if (refresh) {
      clearCache(el);
    }

    var img = _doc.importNode(el, true);
    var parsed = parseSrcsetCached(img);
    var webps = parsed.webps;
    var nowebps = parsed.nowebps;

    if (!webps.length || !nowebps.length) {
      return false;
    }

    var picture = $.create(PICTURE);
    var source = $.create(SOURCE);

    var dataset = $.attr(img, DATA_SRCSET);

    if (dataset) {
      $.attr(source, DATA_SRCSET, webps.join(',').trim());
      $.attr(img, DATA_SRCSET, nowebps.join(',').trim());
    }
    else {
      source.srcset = webps.join(',').trim();
      img.srcset = nowebps.join(',').trim();
    }

    var sizes = $.attr(img, 'sizes');
    if (sizes) {
      source.sizes = sizes;
    }

    source.type = MIME_WEBP;

    $.append(picture, source);
    $.append(picture, img);

    return picture;
  }

  function run(elms) {
    if (isSupported() || !elms.length) {
      return;
    }

    $.each(elms, function (el) {
      if (!$.equal(el, 'img')) {
        return;
      }

      if ($.isElm($.closest(el, PICTURE))) {
        return;
      }

      var parent = $.closest(el, '.media') || el.parentNode;
      var picture = convert(el);

      if (picture) {
        // Cannot use parent.replaceWith because this is for old browsers.
        // Nor parent.replaceChild(picture, el); due to various features.
        $.append(parent, picture);
        $.remove(el);
      }
    });
  }

  // Prefilter caller.
  function init(me) {
    if (isSupported()) {
      return;
    }

    var sel = function (prefix) {
      prefix = prefix || '';
      return $.selector(me.options, '[' + prefix + 'srcset*=".webp"]');
    };

    var elms = $.findAll(_doc, sel());
    if (!elms.length) {
      elms = $.findAll(_doc, sel('data-'));
    }

    if (elms.length) {
      run(elms);
    }
  }

  $.webp = {
    clearCache: clearCache,
    isSupported: isSupported,
    init: init
  };

})(dBlazy, this, this.document);
