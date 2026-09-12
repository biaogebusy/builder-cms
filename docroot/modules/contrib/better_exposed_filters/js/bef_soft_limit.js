/**
 * @file
 * Provides the soft limit functionality.
 */

(function ($, once) {
  Drupal.behaviors.better_exposed_filters_soft_limit = {
    attach: function (context, settings) {
      if (settings.better_exposed_filters.soft_limit !== 'undefined') {
        $.each(settings.better_exposed_filters.soft_limit, function (bef) {
          Drupal.better_exposed_filters.applySoftLimit(bef, settings.better_exposed_filters.soft_limit[bef]);
        });
      }
    }
  };

  Drupal.better_exposed_filters = Drupal.better_exposed_filters || {};

  /**
   * Applies the soft limit UI feature to a specific filter.
   *
   * @param {string} filter_id
   *   The filter id.
   * @param {object} settings
   *   The maximum amount of items to show.
   */
  Drupal.better_exposed_filters.applySoftLimit = function (filter_id, settings) {
    var filter_selector = filter_id.replace(/_/g, '-');
    var zero_based_limit = (settings.limit - 1);
    // Use exact match to avoid matching filters with similar name prefixes.
    var bef_list = $('[data-drupal-selector="edit-' + filter_selector + '"] ' + settings.list_selector);

    // In case of multiple instances of a filter, we need to key them.
    if (bef_list.length > 1) {
      bef_list.each(function (key, $value) {
        $(this).attr('data-drupal-filter-id', filter_selector + '-' + key);
      });
    }

    // Use a unique once key per filter to avoid conflicts between filters.
    var onceKeyItems = 'applySoftLimit-items-' + filter_id;
    var onceKeyLinks = 'applySoftLimit-links-' + filter_id;

    // Capture settings values in local variables for the closure.
    var itemSelector = settings.item_selector;
    var showLessLabel = settings.show_less;
    var showMoreLabel = settings.show_more;

    var isActiveItem = function () {
      var item = $(this);
      return item.is('.bef-link--selected, input:checked') ||
        item.find('.bef-link--selected, input:checked').length > 0;
    };

    var getSoftLimitedItems = function (bef) {
      return bef.find(itemSelector).slice(zero_based_limit + 1).filter(function () {
        return !isActiveItem.call(this);
      });
    };

    // Hide inactive befs over the limit.
    bef_list.each(function () {
      $(once(onceKeyItems, getSoftLimitedItems($(this)))).hide();
    });

    // Add "Show more" / "Show less" links.
    $(once(onceKeyLinks, bef_list.filter(function () {
      return getSoftLimitedItems($(this)).length > 0;
    }))).each(function () {
      let bef = $(this);
      $('<a href="#" class="bef-soft-limit-link"></a>')
        .text(showMoreLabel)
        .on('click', function (e) {
          e.preventDefault();
          let scrollTop = $(window).scrollTop();
          if (getSoftLimitedItems(bef).filter(':hidden').length > 0) {
            bef.find(itemSelector + ':gt(' + zero_based_limit + ')').slideDown();
            bef.find(itemSelector + ':lt(' + (zero_based_limit + 2) + ') a, ' + itemSelector +':lt(' + (zero_based_limit + 2) + ') input').focus();
            $(this).addClass('open').text(showLessLabel);
          }
          else {
            getSoftLimitedItems(bef).slideUp();
            $(this).removeClass('open').text(showMoreLabel);
          }
          setTimeout(function () {
            $(window).scrollTop(scrollTop);
          }, 0);
          return false;
        }).insertAfter($(this));
    });
  };

})(jQuery, once);
