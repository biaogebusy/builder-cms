/**
 * @file
 * Provides Intersection Observer API loader for media.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 */

(function ($, _bio, _win, _doc) {
  'use strict';

  /**
   * Private variables.
   */
  var VARS = {
    src: 'src',
    srcset: 'srcset',
    dataHtml: 'data-b-html',
    dataSrc: 'data-src',
    dataSrcset: 'data-srcset',
    dataText: 'data:text/plain;base64,',
    imgSources: ['srcset', 'src'],
    erCount: 0
  };

  var FN_MULTIMEDIA = $.multimedia || false;
  var SUPER = _bio.prototype;
  var FN;

  /**
   * Constructor for BioMedia, Blazy IntersectionObserver for media.
   *
   * @param {object} options
   *   The BioMedia options.
   *
   * @return {object}
   *   The BioMedia instance.
   *
   * @namespace
   */
  function BioMedia(options) {
    // @todo revert if any issues
    // var me = _bio.apply($.extend({}, SUPER, $.extend({}, FN, this)), arguments);
    var base = $.extend({}, SUPER, FN);
    var me = _bio.call(base, options);

    me.name = 'BioMedia';

    return me;
  }

  // Establish inheritance.
  FN = BioMedia.prototype = Object.create(SUPER);
  FN.constructor = BioMedia;

  /**
   * Attempts to fix for Views rewrite stripping out data URI causing 404.
   *
   * This is not needed by `No JavaScript` version due to no placeholders.
   *
   * E.g.: src="image/jpg;base64 should be src="data:image/jpg;base64.
   * The browsers load it as https://mysite.com/image/jpg... which causes 404.
   * The "Placeholder" 1px.gif via Blazy UI costs extra HTTP requests. This is
   * a less costly solution, but not bulletproof due to being client-side
   * which means too late to the party. Yet not bad for 404s below the fold.
   * This must be run before any lazy (native, bLazy or IO) kicks in.
   *
   * @param {Object} img
   *  The IMG element, expected.
   * @return {boolean}
   *   True if it has broken data URI.
   *
   * @todo Remove if a permanent non-client available other than Placeholder.
   */
  function fixDataUri(img) {
    if (!img || img.nodeName !== 'IMG') {
      return false;
    }

    var src = img.getAttribute(VARS.src);
    if (!src || typeof src !== 'string') {
      return false;
    }

    // Fast bail-outs (critical for perf).
    // Already valid.
    if (src.startsWith('data')) {
      return false;
    }

    // Must start with "image".
    // (avoid touching normal URLs like /images/foo.jpg)
    if (!src.startsWith('image')) {
      return false;
    }

    // Must look like a data URI fragment.
    // (avoid breaking legitimate paths like "image/foo.jpg").
    if (src.indexOf('base64') === -1 && src.indexOf('svg+xml') === -1) {
      return false;
    }

    // Fix only the prefix (safe + minimal).
    // Only replace the FIRST occurrence at the start.
    var fixed = 'data' + src;

    img.setAttribute(VARS.src, fixed);

    return true;
  }

  function mapAttributes(el) {
    // Only map attributes once.
    if (!el._bioMapped) {
      // Ensure to not mess up with blur which requires animation trigger.
      if ($.hasAttr(el, 'data-src') && !$.hasClass(el, 'b-blur')) {
        // Reset attributes, and let supportive browsers lazy load natively.
        $.mapAttr(el, VARS.imgSources, true);

        // Also supports PICTURE which contains SOURCEs. Excluding VIDEO.
        $.mapSource(el, false, true, false);
      }
      el._bioMapped = true;
    }
  }

  // Load a HTML content.
  function loadHtml(cn, opts) {
    if ($.isHtml(cn) && $.hasAttr(cn, VARS.dataHtml)) {
      var html = $.attr(cn, VARS.dataHtml);
      var status = false;

      if (html) {
        status = true;
        html = html.replace(VARS.dataText, '');
        html = atob(html);

        $.append(cn, html);
        $.removeAttr(cn, VARS.dataHtml);
      }
      VARS.erCount = $.status(cn, status, opts);
    }
  }

  // Load local media (audio/video).
  function loadLocalMedia(el, status, opts) {
    // Native doesn't support video, fix it.
    $.mapSource(el, VARS.src, true);
    el.load();

    if (FN_MULTIMEDIA) {
      FN_MULTIMEDIA.init(el);
    }
    return $.status(el, status, opts);
  }

  // Since bLazy, which has no supports for Native, is a fallback, it is easier
  // now to work with Native. No more need to hook into load event separately,
  // no deferred invocation till one loaded, no hijacking.
  // No more fights under a single source of truth. It is a total swap.
  // As mentioned in the doc, Native at least Chrome starts loading images
  // 8000px, hardcoded, before they are entering the viewport. Meaning harsh,
  // makes fancy stuffs like blur useless. And bad because blur filter
  // is very expensive, and when they are triggered before visible, will block.
  // @see /admin/help/blazy_ui# NATIVE LAZY LOADING
  // With bIO as the main loader, the game changed, quoted from:
  // https://developer.mozilla.org/en-US/docs/Learn/HTML/Howto/Author_fast-loading_HTML_pages
  // "Note that lazily-loaded images may not be available when the load event is
  // fired. You can determine if a given image is loaded by checking to see if
  // the value of its Boolean complete property is true."
  // Old bLazy relies on onload, meaning too early loaded decision for Native,
  // the reason for our previous deferred invocation, not decoding like what bIO
  // did which is more precise as suggested by the quote.
  // Assumed, untested, fine with combo IO + decoding checks before blur spits.
  // Shortly we are in the right direction to cope with Native vs. data-[SRC].
  // @done recheck IF wrong so to put back https://drupal.org/node/3120696.
  // Almost not wrong, no blur nor `b-loaded` were added till intersected, but
  // added a new `loading:defer` to solve 8000px threshold.
  FN.preprocess = function (el, key, cb) {
    var me = this;
    var opts = me.options;
    var loading = $.attr(el, 'loading');
    var isDataset = $.hasAttr(el, 'data-src');

    // The `a` keyword found in `auto, eager, lazy`, not `defer`.
    // The `defer` should only request attribute replacements on demand to
    // mitigate harsh 8000px Chrome default threshold, if enabled.
    key = key || 'a';

    // Attempts to fix for Views rewrite stripping out data URI causing 404.
    fixDataUri(el);

    // Skip JS entirely if No JavaScript is enabled, unless Native don't
    // understand. Old browsers without loading supports should use [data-src].
    if (opts.isNative &&
      $.contains(loading, 'a') &&
      !isDataset
    ) {
      return;
    }

    // Skip if No JavaScript is enabled.
    // Ensure to not mess up with blur which requires animation trigger.
    if (isDataset &&
      $.contains(loading, key) &&
      !$.hasClass(el, 'b-blur')) {
      // Reset attributes, and let supportive browsers lazy load natively.
      // $.mapAttr(el, ['srcset', VARS.src], true);
      // Also supports PICTURE which contains SOURCEs. Excluding VIDEO.
      // $.mapSource(el, false, true, false);
      mapAttributes(el);

      // Executes a function if any.
      if ($.isFun(cb)) {
        cb(el);
      }
    }
  };

  // Extends Bio prototype.
  FN.lazyLoad = function (el, winData) {
    var me = this;
    var opts = me.options;
    var parent = el.parentNode;
    var isBg = $.isBg(el, opts);
    var isPicture = $.equal(parent, 'picture');
    var isImage = $.equal(el, 'img');
    var isAudio = $.equal(el, 'audio');
    var isVideo = $.equal(el, 'video');
    var isDataset = $.hasAttr(el, VARS.dataSrc);

    // PICTURE elements.
    if (isPicture) {
      if (isDataset) {
        $.mapSource(el, VARS.srcset, true);

        // Tiny controller image inside picture element won't get preloaded.
        $.mapAttr(el, VARS.src, true);
      }

      VARS.erCount = defer(me, el, true, opts);
    }
    // AUDIO/ VIDEO elements.
    else if (isVideo || isAudio) {
      // Multi contents: BG + real elements, just audio since it has no poster.
      if ($.isBg(parent, opts)) {
        me.loadImage(parent, true, winData);
      }

      VARS.erCount = loadLocalMedia(el, true, opts);
    }
    else {
      // IMG or DIV/ block elements got preloaded for better UX with loading.
      // Native doesn't support DIV, fix it.
      if (isImage || isBg) {
        me.loadImage(el, isBg, winData);

        // Double lazy load elements.
        if (isBg && $.isHtml(el)) {
          loadHtml(el, opts);
        }
      }
      else {
        // IFRAME elements, etc.
        if ($.hasAttr(el, VARS.src)) {
          if ($.attr(el, VARS.dataSrc)) {
            $.mapAttr(el, VARS.src, true);
          }

          VARS.erCount = defer(me, el, true, opts);
        }
        // HTML elements.
        else {
          loadHtml(el, opts);
        }
      }
    }

    me.erCount = VARS.erCount;
  };

  // Compatibility between Native and old data-[SRC|SRSET] approaches.
  FN.loadImage = function (el, isBg, winData) {
    var me = this;
    var opts = me.options;

    isBg = isBg || $.isBg(el, opts);

    var isResimage = $.hasAttr(el, VARS.srcset);
    var isDataset = $.hasAttr(el, VARS.dataSrc);
    var currSrc = isDataset ? VARS.dataSrc : VARS.src;
    var currSrcset = isDataset ? VARS.dataSrcset : VARS.srcset;
    var img;

    var load = function (el, ok) {
      if (isBg && $.isFun($.bg)) {
        $.bg(el, winData);
        VARS.erCount = $.status(el, ok, opts);
      }
      else {
        VARS.erCount = defer(me, el, ok, opts);
      }
    };

    // Bail out early if already loaded.
    if (!isBg && $.isDecoded(el)) {
      load(el, true);
      return;
    }

    img = new Image();
    var preload = function () {
      if ('decode' in img) {
        img.decoding = 'async';
      }

      if (isBg && $.isFun($.bgUrl)) {
        img.src = $.bgUrl(el, winData);
      }
      else {
        if (isDataset) {
          $.mapAttr(el, VARS.imgSources, false);
        }

        img.src = $.attr(el, currSrc);
      }

      if (isResimage) {
        img.srcset = $.attr(el, currSrcset);
        // Copy the element sizes attribute so the browser pre-loads the correct
        // image from the srcset when using width descriptors.
        var imageSizes = $.attr(el, 'sizes');
        if (imageSizes) {
          img.sizes = imageSizes;
        }
      }
    };

    preload();

    // Preload `img` to have correct event handlers.
    $.decode(img)
      .then(function () {
        load(el, true);

        return img;
      })
      .catch(function () {
        load(el, false);

        // Allows to re-observe.
        el.bhit = false;
      });
  };

  FN.resizing = function (el, winData) {
    var me = this;
    var isBg = $.isBg(el, me.options);

    // Fix dynamic multi-breakpoint background to avoid loaders workarounds.
    if (isBg) {
      me.loadImage(el, isBg, winData);
    }
  };

  // Applies the defer loading as per https://drupal.org/node/3120696.
  // This replaces all loading=defer into original loading=lazy once the first
  // row of images is found to solve the hard-coded threshold 8000px problems.
  // Basically telling browsers to delay lazyloading until one is nearly
  // visible, not immediately lazyloaded at 8000px down the viewport which makes
  // expectations useless such as for blurs, loading animation, interactive
  // elements on the exact moment of loading/ visible event, etc. If you hate
  // cool kids or fancy stuffs, do not choose `defer` option, no fuss.
  function defer(me, el, status, opts) {
    if (!el._isDeferred) {
      me.preprocess(el, 'defer', function () {
        $.attr(el, 'loading', 'lazy');
      });

      el._isDeferred = true;
    }

    // @todo enable el._bioLoading = false;
    return $.status(el, status, opts);
  }

  FN.prepare = function () {
    var me = this;

    // Runs after native set to minimize works.
    if ($.webp) {
      $.webp.init(me);
    }
  };

  _win.BioMedia = BioMedia;

})(dBlazy, Bio, this, this.document);
