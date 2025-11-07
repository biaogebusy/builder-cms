/**
 * @file
 * Contains the definition of the behaviour entityShareClientFullPager.
 */

(function (Drupal, once) {

  'use strict';

  /**
   * Attaches the JS behavior to remove unneeded query parameters.
   *
   * Those parameters are added because the pager is loaded in an Ajax request.
   *
   * @see https://www.drupal.org/node/3064252
   * @see https://www.drupal.org/node/2504709
   */
  Drupal.behaviors.entityShareClientFullPager = {
    attach: function (context, settings) {
      var pagerLinks = once(
        'js--full-pager',
        '.js-pager__items a',
        context
      );
      pagerLinks.forEach(prepareLink);
    }
  };

  function prepareLink(element) {
    var href = element.getAttribute('href');
    href = removeUrlParameter(href, 'ajax_form');
    href = removeUrlParameter(href, '_wrapper_format');
    element.setAttribute('href', href);
  }

  /**
   * Helper function to remove a query parameter from a string.
   *
   * @param {string} url
   *   The URL to remove the query parameter.
   * @param {string} parameter
   *   The query parameter to remove.
   *
   * @return {string}
   *   The URL without the query parameter.
   */
  function removeUrlParameter(url, parameter) {
    return url
      .replace(new RegExp('([\?&]{1})' + parameter + '=[^&]*'), '$1') // eslint-disable-line no-useless-escape
      .replace(/\?&/, '?')
      .replace(/&&/, '&')
      .replace(/&$/, '');
  }

})(Drupal, once);
