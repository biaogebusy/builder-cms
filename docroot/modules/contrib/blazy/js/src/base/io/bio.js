/**
 * @file
 * Provides Intersection Observer API loader.
 *
 * This file is not loaded when `No JavaScript` enabled, unless exceptions met.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 * @see https://www.npmjs.com/package/intersection-observer
 * @see https://github.com/w3c/IntersectionObserver
 * @see https://caniuse.com/?search=visualViewport
 * @todo https://developer.mozilla.org/en-US/docs/Web/API/Visual_Viewport_API
 * @todo remove traces of fallback to be taken care of by old bLazy fork.
 */

(function ($, _win, _doc) {

  'use strict';

  /**
   * Private variables.
   */
  var VARS = {
    nick: 'bio',
    winData: {},
    bioTick: 0,
    revTick: 0,
    hitTick: 0,
    bgClass: 'b-bg',
    isVisibleClass: 'is-b-visible',
    eIntersecting: 'bio:intersecting',
    sParent: '.media',
    addClass: 'addClass',
    removeClass: 'removeClass',
    initialized: false,
    isNative: $.isNativeLazy,
    isResizing: false,
    validateDelay: 25,
    ww: 0
  };
  var ROOT = _doc;
  var OPTS = {};
  var FN_OBSERVER = $.observer;
  var FN_VIEWPORT = $.viewport;
  var FN;

  /**
   * Constructor for Bio, Blazy IntersectionObserver.
   *
   * @param {object} options
   *   The Bio options.
   *
   * @return {Bio}
   *   The Bio instance.
   *
   * @namespace
   */
  function Bio(options) {
    var me = $.extend({}, FN, this);

    me.name = 'Bio';
    me.options = OPTS = $.extend({}, $._defaults, options || {});
    me.options.isNative = VARS.isNative;

    VARS.bgClass = OPTS.bgClass || VARS.bgClass;
    VARS.validateDelay = OPTS.validateDelay || VARS.validateDelay;
    VARS.sParent = OPTS.parent || VARS.sParent;
    ROOT = OPTS.root || ROOT;

    // DOM ready fix. Ain't a culprit.
    $.ready(function () {
      me.reinit();
    });

    return me;
  }

  function intersecting(el, revalidate) {
    var me = this;
    var opts = me.options;
    var sel = opts.selector;
    var count = me.count;
    var io = me.ioObserver;
    var watching = opts.visibleClass || revalidate || false;

    // Only destroy if no use for is-b-visible class.
    if (VARS.bioTick === count - 1) {
      $.trigger(_win, VARS.nick + ':done', [me, opts]);

      if (!watching) {
        me.destroyQuietly();
      }
    }

    // Unlike ResizeObserver/ infinite pager, IntersectionObserver is done.
    if (io) {
      // We are here with arbitrary observed elements for hidden children.
      // See https://drupal.org/node/3279316.
      var hidden = FN_OBSERVER.hiddenChild(el, sel);
      if (hidden) {
        el = hidden;
      }

      if (me.isLoaded(el) && !revalidate) {
        // Unless watching.
        if (opts.isMedia && !watching) {
          io.unobserve(el);
        }

        // Count the loaded ones, watching or not.
        VARS.bioTick++;
      }
    }

    // Image may take time to load after being hit, and it may be intersected
    // several times till marked loaded. Ensures it is hit once regardless
    // of being loaded, or not. No real issue with normal images on the page,
    // until having VIS alike which may spit out new images on AJAX request.
    if (!el.bhit || revalidate) {
      // Makes sure to have media loaded beforehand.
      me.lazyLoad(el, VARS.winData);

      VARS.hitTick++;

      // Marks it hit/ requested, not necessarily loaded.
      el.bhit = true;
    }

    // If not extending/ overriding, at least provide the option.
    if ($.isFun(opts.intersecting)) {
      opts.intersecting(el, opts);
    }

    // If not extending/ overriding, also allows to listen to.
    $.trigger(el, VARS.eIntersecting, [me, opts]);
  }

  // This function is called by two observers: IO and RO.
  function interact(entries) {
    var me = this;
    var opts = me.options;
    var vp = FN_VIEWPORT.vp || {};
    var ww = FN_VIEWPORT.ww || 0;
    var entry = entries[0];
    var isBlur = $.isBlur(entry);
    var isResizing = FN_VIEWPORT.isResized(me, entry);
    var visibleClass = opts.visibleClass;
    var forAnim = $.isBool(visibleClass) && visibleClass;

    // RO is another abserver.
    if (isResizing) {
      VARS.winData = FN_VIEWPORT.update(opts);

      FN_VIEWPORT.onresizing(me, VARS.winData);

      if (VARS.ww > 0) {
        var details = {
          winData: VARS.winData,
          entries: me.elms,
          currentWidth: ww,
          oldWidth: VARS.ww,
          enlarged: ww > VARS.ww
        };

        // Ensures only before settled, or if any different from previous size.
        if (VARS.ww !== ww) {
          $.trigger(_win, VARS.nick + ':resizing', details);
        }
        else {
          $.trigger(_win, VARS.nick + ':resized', details);
        }
        me.resizeTick++;
      }
    }
    else {
      // Stop IO watching if destroyed, unless a visibleClass is defined:
      // Animation, BG color on being visible, infinite pager, or lazyloaded
      // blocks. Infinite pager is a valid sample since it has a single link
      // to observe for infinite click events. Unobserve should be left to them.
      if (me.destroyed && !visibleClass) {
        return;
      }
    }

    // Load each on entering viewport.
    $.each(entries, function (e) {
      var target = e.target;
      var el = target || e;
      var resized = FN_VIEWPORT.isResized(me, e);
      var visible = FN_VIEWPORT.isVisible(e, vp);
      var cn = el._bioParent || el;

      isBlur = isBlur && !$.hasClass(cn, 'is-b-animated');

      // The element is being intersected.
      if (visible) {
        // Triggers loading indicator animation before being loaded.
        if (!me.isLoaded(el)) {
          $[VARS.addClass](cn, VARS.isVisibleClass);
        }

        intersecting.call(me, el);

        // The intersecting does the loading, the check must be afterwards.
        // To make efficient blur filter via CSS, etc. Blur filter is expensive.
        if (me.isLoaded(el)) {
          if (isBlur || forAnim) {
            $[VARS.addClass](cn, VARS.isVisibleClass);
          }

          if (!forAnim) {
            setTimeout(function () {
              $[VARS.removeClass](cn, VARS.isVisibleClass);
            }, 601);
          }
        }
      }
      else {
        $[VARS.removeClass](cn, VARS.isVisibleClass);
      }

      // For different toggle purposes regardless being loaded, or not.
      // Avoid using the reserved `is-b-visible`, use `is-b-inview`, etc.
      if (visibleClass && $.isStr(visibleClass)) {
        $[visible ? VARS.addClass : VARS.removeClass](cn, visibleClass);
      }

      // The element is being resized.
      VARS.isResizing = resized && VARS.ww > 0;
      if (VARS.isResizing && !isBlur) {
        // Ensures only before settled, or if any different from previous size.
        if (VARS.ww !== ww) {
          me.resizing(el, VARS.winData);
        }
      }

      // Provides option such as to animate bg or elements regardless position.
      // See gridstack.parallax.js.
      if ($.isFun(opts.observing)) {
        opts.observing(e, visible, opts);
      }
    });

    VARS.ww = ww;
  }

  function verify(me, elms, cb) {
    if (elms.length) {
      $.each(elms, function (el) {
        if (!el._bioParent) {
          el._bioParent = $.closest(el, VARS.sParent) || el;
          me.preprocess(el);

          if ($.isFun(cb)) {
            cb(el);
          }
        }
      });
    }
  }

  // Initializes the IO with fallback to old bLazy.
  function init(me) {
    me.elms = $.findAll(ROOT, $.selector(me.options));
    me.count = me.elms.length;

    if (!me.elms.length) {
      return;
    }

    verify(me, me.elms);

    // Swap data-[SRC|SRCSET] for non-js version once, if not choosing Native.
    // Native lazy markup is triggered by enabling `No JavaScript` lazy option.
    me.prepare();

    me._raf = [];
    me._queue = [];
    me.withIo = true;

    // Observe elements. Old blazy as fallback is also initialized here.
    // IO will unobserve, or disconnect. Old bLazy will self destroy.
    me.observe(true);
  }

  // Cache our prototype.
  FN = Bio.prototype;
  FN.constructor = Bio;

  // Prepare prototype to interchange with Blazy as fallback.
  FN.count = 0;
  FN.erCount = 0;
  FN.resizeTick = 0;
  FN.destroyed = false;
  FN.elms = [];
  FN.options = {};
  FN.preprocess = function (el, key, cb) { };
  FN.lazyLoad = function (el, winData) { };
  FN.loadImage = function (el, isBg, winData) { };
  FN.resizing = function (el, winData) { };
  FN.prepare = function () { };
  FN.windowData = function () {
    return $.isUnd(VARS.winData.vp) ? FN_VIEWPORT.windowData(this.options, true) : VARS.winData;
  };

  // BC for interchanging with bLazy.
  // @todo merge with bLazy::load.
  FN.load = function (elms, revalidate, opts) {
    var me = this;

    elms = elms && $.toArray(elms);

    // @todo remove once infinite pager regression fixed properly like before.
    if (!$.isUnd(opts)) {
      me.options = $.extend({}, me.options, opts || {});
    }

    // Re-use old existing loadInvisible to revalidate hidden elements.
    revalidate = revalidate || me.options.loadInvisible;

    // Manually load elements regardless of being disconnected, or not, relevant
    // for Slick slidesToShow > 1 which rebuilds clones of unloaded elements.
    verify(me, elms, function (el) {
      if (!me.isLoaded(el) || ($.isElm(el) && revalidate)) {
        if (!el._bioValidated) {
          el._bioValidated = true;

          me.elms.push(el);
        }

        intersecting.call(me, el, revalidate);
      }
    });
  };

  FN.isLoaded = function (el) {
    if ($.isElm(el)) {
      // @todo remove class-based check after another check.
      return el._bioLoaded || $.hasClass(el, this.options.successClass);
    }
    return false;
  };

  FN.isNotLoaded = function (el) {
    return !this.isLoaded(el);
  };

  // @todo remove, no longer needed since 2.18 with event-based bio.ajax.
  FN.revalidate = function (force) {
    var me = this;

    // Prevents from too many revalidations unless needed.
    if ((force === true || me.count !== VARS.hitTick) && (VARS.revTick < VARS.hitTick)) {
      var elms = $.findAll(ROOT, $.selector(me.options));

      if (elms.length) {
        verify(me, elms, function (el) {
          if (!me.isLoaded(el) && !el._bioValidated) {
            el._bioValidated = true;

            me.elms.push(el);
          }
        });

        me.observe(true);

        VARS.revTick++;
      }
    }
  };

  FN.destroyQuietly = function (force) {
    var me = this;
    var opts = me.options;

    // Infinite pager like IO wants to keep monitoring infinite contents.
    // Multi-breakpoint BG/ ratio may want to update during resizing.
    if (!me.destroyed && (force || $.isUnd(Drupal.io))) {
      var el = $.find(_doc, $.selector(opts, ':not(.' + opts.successClass + ')'));

      if (!$.isElm(el)) {
        me.destroy(force);
      }
    }
  };

  FN.destroy = function (force) {
    var me = this;
    var opts = me.options;
    var io = me.ioObserver;
    var done = (VARS.bioTick === me.count - 1);
    var disconnect = done && opts.disconnect;

    // Do not disconnect if any error found.
    if (me.destroyed || (me.erCount > 0 && !force)) {
      return;
    }

    // Disconnect when all entries are loaded, if so configured.
    if (disconnect || force) {
      if (io) {
        io.disconnect();
      }

      FN_OBSERVER.unload();
      me.count = 0;
      me.elms = [];
      me.ioObserver = null;
      me.destroyed = true;
    }
  };

  FN.observe = function (reobserve) {
    var me = this;

    reobserve = reobserve || me.destroyed;

    // Observe as IO, or initialize old bLazy as fallback.
    if (!VARS.initialized || reobserve) {
      VARS.winData = FN_OBSERVER.init(me, interact, me.elms, true);

      me.destroyed = false;

      FN_OBSERVER.observe();

      VARS.initialized = true;
    }
  };

  FN.reinit = function () {
    var me = this;

    me.destroyed = true;
    VARS.bioTick = 0;

    init(me);
  };

  _win.Bio = Bio;

})(dBlazy, this, this.document);
