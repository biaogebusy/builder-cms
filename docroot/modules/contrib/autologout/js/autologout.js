/**
 * @file
 * JavaScript for autologout.
 */

(function ($, Drupal, cookies) {

  'use strict';

  /**
   * Used to lower the cpu burden for activity tracking on browser events.
   */
  function debounce(f) {
      var timeout;
      return function () {
          var savedContext = this;
          var savedArguments = arguments;
          var finalRun = function () {
              timeout = null;
              f.apply(savedContext, savedArguments);
          };

          if (!timeout) {
            f.apply(savedContext, savedArguments);
          }
          clearTimeout(timeout);
          timeout = setTimeout(finalRun, 500);
      };
  }

  /**
   * Attaches the batch behavior for autologout.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.autologout = {
    attach: function (context, settings) {
      if (context !== document) {
        return;
      }

      var paddingTimer;
      var theDialog;
      var t;
      var localSettings;

      // Activity is a boolean used to detect a user has
      // interacted with the page.
      var activity;

      // Timer to keep track of activity resets.
      var activityResetTimer;

      // Prevent settings being overridden by ajax callbacks by cloning it.
      localSettings = jQuery.extend(true, {}, settings.autologout);

      // Add timer element to prevent detach of all behaviours.
      var timerMarkup = $('<div id="timer"></div>').hide();
      $('body').append(timerMarkup);

      if (localSettings.refresh_only) {
        // On pages where user shouldn't be logged out, don't set the timer.
        t = setTimeout(keepAlive, localSettings.timeout);
      }
      else {
        // Set no activity to start with.
        activity = false;

        if (localSettings.logout_regardless_of_activity) {
          // Ignore users activity and set timeout.
          var timestamp = Math.round((new Date()).getTime() / 1000);
          var login_time = cookies.get("Drupal.visitor.autologout_login");
          var difference = (timestamp - login_time) * 1000;

          t = setTimeout(init, localSettings.timeout - difference);
        }
        else {
          // Bind formUpdated events to preventAutoLogout event.
          $('body').bind('formUpdated', debounce(function (event) {
            $(event.target).trigger('preventAutologout');
          }));

          // Bind formUpdated events to preventAutoLogout event.
          $('body').bind('mousemove', debounce(function (event) {
            $(event.target).trigger('preventAutologout');
          }));

          // Support for CKEditor.
          if (typeof CKEDITOR !== 'undefined') {
            CKEDITOR.on('instanceCreated', function (e) {
              e.editor.on('contentDom', function () {
                e.editor.document.on('keyup', debounce(function (event) {
                  // Keyup event in ckeditor should prevent autologout.
                  $(e.editor.element.$).trigger('preventAutologout');
                }));
              });
            });
          }

          $('body').bind('preventAutologout', function (event) {
            // When the preventAutologout event fires, we set activity to true.
            activity = true;

            // Clear timer if one exists.
            clearTimeout(activityResetTimer);

            // Set a timer that goes off and resets this activity indicator after
            // a minute, otherwise sessions never timeout.
            activityResetTimer = setTimeout(function () {
              activity = false;
            }, 60000);
          });

          // On pages where the user should be logged out, set the timer to popup
          // and log them out.
          t = setTimeout(init, localSettings.timeout);
        }
      }

      function init() {
        var noDialog = settings.autologout.no_dialog;
        if (activity) {
          // The user has been active on the page.
          activity = false;
          refresh();
        }
        else {
          // The user has not been active, ask them if they want to stay logged
          // in and start the logout timer.
          paddingTimer = setTimeout(confirmLogout, localSettings.timeout_padding);
          // While the countdown timer is going, lookup the remaining time. If
          // there is more time remaining (i.e. a user is navigating in another
          // tab), then reset the timer for opening the dialog.
          Drupal.Ajax['autologout.getTimeLeft'].autologoutGetTimeLeft(function (time) {
            if (time > 0) {
              clearTimeout(paddingTimer);
              t = setTimeout(init, time);
            }
            else {
              // Logout user right away without displaying a confirmation dialog.
              if (noDialog) {
                logout();
                return;
              }
              theDialog = dialog();
            }
          });
        }
      }

      function dialog() {
        var disableButtons = settings.autologout.disable_buttons;

        var buttons = {};
        if (!disableButtons) {
          var yesButton = settings.autologout.yes_button;
          buttons[Drupal.t(yesButton)] = function () {
            cookies.set("Drupal.visitor.autologout_login", Math.round((new Date()).getTime() / 1000));
            $(this).dialog("destroy");
            clearTimeout(paddingTimer);
            refresh();
          };

          var noButton = settings.autologout.no_button;
          buttons[Drupal.t(noButton)] = function () {
            $(this).dialog("destroy");
            logout();
          };
        }

        return $('<div id="autologout-confirm">' + localSettings.message + '</div>').dialog({
          modal: true,
          closeOnEscape: false,
          width: localSettings.modal_width,
          dialogClass: 'autologout-dialog',
          title: localSettings.title,
          buttons: buttons,
          close: function (event, ui) {
            logout();
          }
        });
      }

      // A user could have used the reset button on the tab/window they're
      // actively using, so we need to double check before actually logging out.
      function confirmLogout() {
        $(theDialog).dialog('destroy');

        Drupal.Ajax['autologout.getTimeLeft'].autologoutGetTimeLeft(function (time) {
          if (time > 0) {
            t = setTimeout(init, time);
          }
          else {
            logout();
          }
        });
      }

      function triggerLogoutEvent(logoutMethod, logoutUrl) {
        const logoutEvent = new CustomEvent('autologout', {
          detail: {
            logoutMethod: logoutMethod,
            logoutUrl: logoutUrl,
          },
        });
        document.dispatchEvent(logoutEvent);
      }

      function logout() {
        if (localSettings.use_alt_logout_method) {
          var logoutUrl = drupalSettings.path.baseUrl + "autologout_alt_logout";
          triggerLogoutEvent('alternative', logoutUrl);

          window.location = logoutUrl;
        }
        else {
          $.ajax({
            url: drupalSettings.path.baseUrl + "autologout_ajax_logout",
            type: "POST",
            beforeSend: function (xhr) {
              xhr.setRequestHeader('X-Requested-With', {
                toString: function () {
                  return '';
                }
              });
            },
            success: function () {
              var logoutUrl = localSettings.redirect_url;
              triggerLogoutEvent('normal', logoutUrl);

              window.location = logoutUrl;
            },
            error: function (XMLHttpRequest, textStatus) {
              if (XMLHttpRequest.status === 403 || XMLHttpRequest.status === 404) {
                window.location = localSettings.redirect_url;
              }
            }
          });
        }
      }

      /**
       * Get the remaining time.
       *
       * Use the Drupal ajax library to handle get time remaining events
       * because if using the JS Timer, the return will update it.
       *
       * @param function callback(time)
       *   The function to run when ajax is successful. The time parameter
       *   is the time remaining for the current user in ms.
       */
      Drupal.Ajax.prototype.autologoutGetTimeLeft = function (callback) {
        var ajax = this;

        if (ajax.ajaxing) {
          return false;
        }
        ajax.options.submit = {
          uactive : activity
        };
        ajax.options.success = function (response, status) {
          if (typeof response == 'string') {
            response = $.parseJSON(response);
          }
          if (typeof response[0].command === 'string' && response[0].command === 'alert') {
            // In the event of an error, we can assume user has been logged out.
            window.location = localSettings.redirect_url;
          }

          callback(response[1].settings.time);

          response[0].data = '<div id="timer" style="display: none;">' + response[0].data + '</div>';

          // Let Drupal.ajax handle the JSON response.
          return ajax.success(response, status);
        };

        try {
          $.ajax(ajax.options);
        }
        catch (e) {
          ajax.ajaxing = false;
        }
      };

      Drupal.Ajax['autologout.getTimeLeft'] = Drupal.ajax({
        base: null,
        element: document.body,
        url: drupalSettings.path.baseUrl + 'autologout_ajax_get_time_left',
        submit: {
          uactive : activity
        },
        event: 'autologout.getTimeLeft',
        error: function (XMLHttpRequest, textStatus) {
          // Disable error reporting to the screen.
        },
      });

      /**
       * Handle refresh event.
       *
       * Use the Drupal ajax library to handle refresh events because if using
       * the JS Timer, the return will update it.
       *
       * @param function timerFunction
       *   The function to tell the timer to run after its been restarted.
       */
      Drupal.Ajax.prototype.autologoutRefresh = function (timerfunction) {
        var ajax = this;

        if (ajax.ajaxing) {
          return false;
        }

        ajax.options.success = function (response, status) {
          if (typeof response === 'string') {
            response = $.parseJSON(response);
          }
          if (typeof response[0].command === 'string' && response[0].command === 'alert') {
            // In the event of an error, we can assume the user has been logged out.
            window.location = localSettings.redirect_url;
          }

          t = setTimeout(timerfunction, localSettings.timeout);
          activity = false;

          // Wrap response data in timer markup to prevent detach of all behaviors.
          response[0].data = '<div id="timer" style="display: none;">' + response[0].data + '</div>';

          // Let Drupal.ajax handle the JSON response.
          return ajax.success(response, status);
        };

        try {
          $.ajax(ajax.options);
        }
        catch (e) {
          ajax.ajaxing = false;
        }
      };

      Drupal.Ajax['autologout.refresh'] = Drupal.ajax({
        base: null,
        element: document.body,
        url: drupalSettings.path.baseUrl + 'autologout_ajax_set_last',
        event: 'autologout.refresh',
        error: function (XMLHttpRequest, textStatus) {
          // Disable error reporting to the screen.
        }
      });

      function keepAlive() {
        Drupal.Ajax['autologout.refresh'].autologoutRefresh(keepAlive);
      }

      function refresh() {
        Drupal.Ajax['autologout.refresh'].autologoutRefresh(init);
      }

      // Check if the page was loaded via a back button click.
      var $dirty_bit = $('#autologout-cache-check-bit');
      if ($dirty_bit.length !== 0) {
        if ($dirty_bit.val() === '1') {
          // Page was loaded via back button click, we should refresh the timer.
          refresh();
        }

        $dirty_bit.val('1');
      }
    }
  };

})(jQuery, Drupal, window.Cookies);
