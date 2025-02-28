/**
 * @file
 * JSON:API Extras table with collapsible rows behaviors.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Handles the events to collapse/expand rows.
   */
  Drupal.behaviors.jsonapi_extras_expandable_rows_table = {
    attach: function (context, settings) {
      var $advanced_opts_links = $(once('toggle-expanded', '.toggle-expanded', context));

      $advanced_opts_links.click(function () {
        $(this).removeClass("content-collapsed content-expanded");
        // 'data-open' attribute holds the id of the element that we should display/hide.
        var $el = $('#' + $(this).attr('data-open'));
        $el.toggle();
        if ($el.is(':visible')){
          $(this).addClass("content-expanded");
        }
        else {
          $(this).addClass("content-collapsed");
        }
      })
    }
  }

}(jQuery, Drupal));
