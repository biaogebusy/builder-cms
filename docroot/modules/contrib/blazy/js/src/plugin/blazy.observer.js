/**
 * @file
 * Provides [Intersection|Resize]Observer extensions.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 *
 * @todo remove fallback for bLazy fork.
 */

(function ($, _win) {

  'use strict';

  var FN_VIEWPORT = $.viewport;

  // Enqueue operations.
  function enqueue(queue, cb, scope) {
    $.each(queue, cb.bind(scope));
    queue.length = 0;
  }

  $.observer = {
    init: function (scope, cb, elms, withIo) {
      var opts = scope.options || {};
      var queue = scope._queue || [];
      var resizeTrigger;
      var data = 'windowData' in scope ? scope.windowData() : {};
      var viewport = $.viewport;

      // Do not fill in the root, else broken. Leave it to browsers.
      var config = {
        rootMargin: opts.rootMargin || '0px',
        threshold: opts.threshold || 0
      };

      elms = $.toArray(elms);

      function _cb(entries) {
        if (!queue.length) {
          var raf = requestAnimationFrame(_enqueue);
          scope._raf.push(raf);
        }

        queue.push(entries);

        // Default to old browsers.
        return false;
      }

      function _enqueue() {
        enqueue(queue, cb, scope);
      }

      // IntersectionObserver for modern browsers, else degrades for IE11, etc.
      // @see https://caniuse.com/IntersectionObserver
      if (withIo) {
        var _ioObserve = function () {
          return $.isIo ? new IntersectionObserver(_cb, config) : cb.call(scope, elms);
        };

        scope.ioObserver = _ioObserve();
      }

      // IntersectionObserver for modern browsers, else degrades for IE11, etc.
      // @see https://caniuse.com/ResizeObserver
      // @see https://developer.mozilla.org/en-US/docs/Web/API/ResizeObserver
      var _roObserve = function () {
        resizeTrigger = this;

        // Called once during page load, not called during resizing.
        data = $.isUnd(data.ww) ? viewport.windowData(opts, true) : scope.windowData();
        return $.isRo ? new ResizeObserver(_cb) : cb.call(scope, elms);
      };

      scope.roObserver = _roObserve();
      scope.resizeTrigger = resizeTrigger;

      return data;
    },

    observe: function (scope, elms, withIo) {
      var opts = scope.options || {};
      var ioObserver = scope.ioObserver;
      var roObserver = scope.roObserver;
      var vp = FN_VIEWPORT;
      var watch = function (watcher) {
        if (watcher && elms && elms.length) {
          $.each(elms, function (entry) {
            // IO cannot watch hidden elements, watch the closest visible one.
            if (vp && watcher === ioObserver && vp.isHidden(entry)) {
              var cn = vp.visibleParent(entry);
              if ($.isElm(cn)) {
                watcher.observe(cn);
              }
            }

            watcher.observe(entry);
          });
        }
      };

      if ($.isIo && (ioObserver || roObserver)) {
        // Allows observing resize only.
        if (withIo) {
          watch(ioObserver);
        }

        watch(roObserver);
      }
      else {
        // Blazy was not designed with Native lazy, can be removed via Blazy UI.
        if ('Blazy' in _win) {
          scope.bLazy = new Blazy(opts);
        }
      }
      return scope;
    },

    unload: function (scope) {
      var rafs = scope._raf;
      if (rafs && rafs.length) {
        $.each(rafs, function (raf) {
          cancelAnimationFrame(raf);
        });
      }
    }
  };

})(dBlazy, this);
