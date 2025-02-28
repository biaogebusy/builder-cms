/**
 * @file
 * Provides CSS3 flex based on Flexbox layout.
 *
 * Credit: https://fjolt.com/article/css-grthis loader id-masonry
 *
 * @requires aspect ratio fluid in the least to layout correctly.
 * @todo deprecated this is worse than NativeGrid Masonry. We can't compete
 * against the fully tested Outlayer or GridStack library.
 */

(function ($, Drupal, _doc) {

  'use strict';

  var ID = 'b-flex';
  var ID_ONCE = ID;
  var C_MOUNTED = 'is-' + ID_ONCE;
  var C_IS_DISABLED = C_MOUNTED + '-disabled';
  var C_IS_CAPTIONED = 'is-b-captioned';
  var S_BASE = '.' + ID;
  // @fixme with lock masonry broken after AJAX unless class removed at detach.
  // drupalSettings.blazy.useAjax ? '.' + ID :
  var S_ELEMENT = S_BASE + ':not(.' + C_MOUNTED + ')';
  var C_GRID = 'grid';
  var S_GRID = '.' + C_GRID;
  var UNLOAD;

  /**
   * Processes a grid object.
   *
   * @param {Object} grid
   *   The grid object.
   */
  function subprocess(grid) {
    // Get the post relayout number of columns.
    var ncol = columnCount(grid._el);

    // If the number of columns has changed.
    if (grid.ncol !== ncol || grid.mod) {
      // Update number of columns.
      grid.ncol = ncol;

      // Revert to initial positioning, no margin.
      var cleanout = function () {
        $.each(grid.items, function (c) {
          c.style.removeProperty('margin-top');
        });
      };

      cleanout();

      // If we have more than one column.
      if (grid.ncol > 1) {
        $.removeClass(grid._el, C_IS_DISABLED);
        $.each(grid.items.slice(ncol), function (c, i) {
          // Bottom edge of item above.
          var prevFin = $.rect(grid.items[i]).bottom;
          // Top edge of current item.
          var currItm = $.rect(c).top;

          c.style.marginTop = (prevFin + grid.gap - currItm) + 'px';
        });
      }
      else {
        $.addClass(grid._el, C_IS_DISABLED);
        cleanout();
      }

      grid.mod = 0;
    }
  }

  function columnCount(elm) {
    var box = $.find(elm, S_GRID);
    var parentWidth = $.rect(elm).width;
    var boxWidth = $.rect(box).width;
    var boxStyle = $.computeStyle(box);
    var margin = parseFloat(boxStyle.marginLeft) + parseFloat(boxStyle.marginRight);
    var itemWidth = boxWidth + margin;
    return Math.round((1 / (itemWidth / parentWidth)));
  }

  function map(elms) {
    return elms.map(function (el) {
      var children = $.slice(el.childNodes);

      return {
        _el: el,
        gap: parseFloat($.computeStyle(_doc.documentElement, '--bf-col-gap', true)),
        items: children.filter(function (c) {
          return $.hasClass(c, C_GRID);
        }),
        ncol: 0,
        count: children.length
      };
    });
  }

  /**
   * Processes a grid masonry.
   *
   * @param {HTMLElement} elm
   *   The container HTML element.
   */
  function process(elm) {
    var caption = $.find(elm, '.views-field') && $.find(elm, '.views-field p');

    // @todo move it to PHP, unreliable here.
    // It is here for Views rows not aware of captions, not formatters.
    if (caption) {
      $.addClass(elm, C_IS_CAPTIONED);
    }

    $.addClass(elm, C_MOUNTED);
  }

  /**
   * Initialize the grid elements.
   *
   * @param {HTMLElement} grids
   *   The container HTML elements.
   */
  function init(grids) {
    var onResize = function (entry) {
      grids.find(function (grid) {
        return grid._el === entry.target.parentElement;
      }).mod = 1;
    };

    var o = new ResizeObserver(function (entries) {
      $.each(entries, onResize);
    });

    grids = map(grids);

    function observe() {
      $.each(grids, function (grid) {
        $.each(grid.items, function (c) {
          o.observe(c);
        });
      });
    }

    function layout(e) {
      if (e === UNLOAD) {
        var elms = $.toElms(S_BASE);

        if (elms.length) {
          grids = map(elms);

          grids.find(function (grid) {
            return $.hasClass(grid._el, ID);
          }).mod = 1;

          $.each(grids, subprocess);
        }
      }
      else {
        $.each(grids, subprocess);
      }
    }

    // Fix for LB or AJAX in general integration.
    // @todo move it to an AJAX event when Drupal has one by 2048.
    if (UNLOAD) {
      setTimeout(function () {

        layout(UNLOAD);
        observe();

        UNLOAD = false;
      }, 700);
    }

    $.on('load.' + ID_ONCE, function () {
      layout();
      observe();

      // No need to debounce, RO is already browser-optimized.
      $.on('resize.' + ID_ONCE, layout, false);
    }, false);
  }

  /**
   * Attaches Blazy behavior to HTML element identified by .b-flex.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyFlex = {
    attach: function (context) {

      var grids = $.once(process, ID_ONCE, S_ELEMENT, context);
      init(grids);

    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        UNLOAD = true;

        setTimeout(function () {
          var els = $.once.removeSafely(ID_ONCE, S_BASE, context);
          if (els && els.length) {
            $.removeClass(els[0], C_MOUNTED);
          }
        });
      }
    }
  };

}(dBlazy, Drupal, this.document));
