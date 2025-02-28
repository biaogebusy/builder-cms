/**
 * @file
 * Provides once compat for D8+.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 *
 * @todo remove most when min D9.2, or take the least minimum for BC.
 * Be sure to make context Element, or patch it to work with [1,9,11] types
 * which distinguish this from core/once as per 2022/2.
 * When removed and context issue is fixed, it will be just:
 * `$.once = $.extend($.once, once);` + `$.once.removeSafely()`.
 * @todo update to and watch out for core/once namespacing change.
 * @see https://www.drupal.org/project/drupal/issues/3254840
 */

(function ($, _win) {

  'use strict';

  var DATA_ONCE = 'data-once';
  var IS_JQ = 'jQuery' in _win;
  var REMOVE = 'remove';
  var SET = 'set';
  var WS_RE = /[\11\12\14\15\40]+/;

  /**
   * A wrapper for core/once until D9.2 is a minimum.
   *
   * @param {Function} cb
   *   The executed function.
   * @param {string} id
   *   The id of the once call.
   * @param {NodeList|Array.<Element>|Element|string} selector
   *   A NodeList, array of elements, single Element, or a string.
   * @param {Document|Element} ctx
   *   An element to use as context for querySelectorAll.
   *
   * @return {Array.<Element>}
   *   An array of elements to process, or empty for old behavior.
   */
  function onceCompat(cb, id, selector, ctx) {
    var els = [];

    // If cb is a string, allow empty selector/ context for document.
    // Assumes once(id, selector, context), by shifting one argument.
    if ($.isStr(cb) && $.isUnd(ctx)) {
      return initOnce(cb, id, selector);
    }

    // Original once.
    if ($.isUnd(selector)) {
      _once(cb);
    }
    // If extra arguments are provided, assumes regular loop over elements.
    else {
      els = initOnce(id, selector, ctx);
      if (els.length) {
        // Already avoids loop for a single item.
        $.each(els, cb);
      }
    }

    return els;
  }

  /**
   * Executes the function once.
   *
   * @private
   *
   * @author Daniel Lamb <dlamb.open.source@gmail.com>
   * @link https://github.com/daniellmb/once.js
   *
   * @param {Function} cb
   *   The executed function.
   *
   * @return {Object}
   *   The function result.
   */
  function _once(cb) {
    var result;
    var ran = false;
    return function proxy() {
      if (ran) {
        return result;
      }
      ran = true;
      result = cb.apply(this, arguments);
      // For garbage collection.
      cb = null;
      return result;
    };
  }

  function _filter(selector, elements, apply) {
    return elements.filter(function (el) {
      var selected = $.is(el, selector);
      if (selected && apply) {
        apply(el);
      }
      return selected;
    });
  }

  function elsOnce(selector, ctx) {
    return $.findAll(ctx, selector);
  }

  function selOnce(id) {
    return '[' + DATA_ONCE + '~="' + id + '"]';
  }

  function updateOnce(el, opts) {
    var add = opts.add;
    var remove = opts.remove;
    var result = [];

    if ($.hasAttr(el, DATA_ONCE)) {
      var ids = $.attr(el, DATA_ONCE).trim().split(WS_RE);
      $.each(ids, function (id) {
        if (!$.contains(result, id) && id !== remove) {
          result.push(id);
        }
      });
    }
    if (add && !$.contains(result, add)) {
      result.push(add);
    }

    var value = result.join(' ');
    $._op(el, value === '' ? REMOVE : SET, DATA_ONCE, value.trim());
  }

  // @todo BigPipe compat to avoid legacy approach with `processed` classes.
  // See:
  // - https://www.drupal.org/project/drupal/issues/1461322.
  // - https://www.drupal.org/project/slick/issues/3340509.
  // - https://www.drupal.org/project/slick/issues/3211873.
  function initOnce(id, selector, ctx) {
    return _filter(':not(' + selOnce(id) + ')', elsOnce(selector, ctx), function (el) {
      updateOnce(el, {
        add: id
      });
    });
  }

  function findOnce(id, ctx) {
    return elsOnce(!id ? '[' + DATA_ONCE + ']' : selOnce(id), ctx);
  }

  $.once = onceCompat;
  $.filter = _filter;

  if (!$.once.find) {
    $.once.find = findOnce;
    $.once.filter = function (id, selector, ctx) {
      return _filter(selOnce(id), elsOnce(selector, ctx));
    };

    // @todo implement clear.
    $.once.remove = function (id, selector, ctx, clear) {
      return _filter(
        selOnce(id),
        elsOnce(selector, ctx),
        function (el) {
          updateOnce(el, {
            remove: id
          });
        }
      );
    };
    $.once.removeSafely = function (id, selector, ctx, clear) {
      var me = this;
      var jq = _win.jQuery;

      // @todo remove BC for pre core/once when min D9.2:
      if (IS_JQ && jq && jq.fn && $.isFun(jq.fn.removeOnce)) {
        jq(selector, $.context(ctx)).removeOnce(id);
      }

      if (me.find(id, ctx).length) {
        return me.remove(id, selector, ctx, clear);
      }
      return [];
    };
  }

})(dBlazy, this);
