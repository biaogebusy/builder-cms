/**
 * @file
 * Attaches behaviors for the zipang captcha refresh module.
 */

(function ($) {
  "use strict";

  /**
   * Attaches jQuery validate behavior to forms.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *  Attaches the outline behavior to the right context.
   */
  Drupal.behaviors.CaptchaRefresh = {
    attach: function (context) {
      $('.reload-captcha', context).not('.processed').bind('click', function () {
        $(this).addClass('processed');
        const $form = $(this).parents('form');
        // Send post query for getting new captcha data.
        const date = new Date();
        const baseUrl = document.location.origin;
        const url = baseUrl.replace(/\/$/g, '') + '/' + $(this).attr('href').replace(/^\//g, '') + '?' + date.getTime();
        // Adding loader.
        $('.captcha').addClass('captcha--loading');
        $.get(
          url,
          {},
          function (response) {
            if (response.status === 1) {
              $('.captcha', $form).find('img').attr('src', response.data.url);
              $('input[name=captcha_sid]', $form).val(response.data.sid);
              $('input[name=captcha_token]', $form).val(response.data.token);
              $('.captcha').removeClass('captcha--loading');
            }
            else {
              alert(response.message);
            }
          },
          'json'
        );
        return false;
      });
    }
  };
})(jQuery);
