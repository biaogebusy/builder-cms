/**
 * @file
 * JavaScript for autologout.
 */

(function ($, Drupal, once) {
  /**
   * Used to lower the cpu burden for activity tracking on browser events.
   *
   * @param {function} f
   *   The function to debounce.
   */
  function debounce(f) {
    let timeout;
    return function (...args) {
      const savedContext = this;
      const finalRun = function () {
        timeout = null;
        f.apply(savedContext, args);
      };
      if (!timeout) {
        f.apply(savedContext, args);
      }
      clearTimeout(timeout);
      timeout = setTimeout(finalRun, 500);
    };
  }

  /**
   * Retrieves a cookie value by name.
   *
   * @param {string} name - The name of the cookie to get.
   * @return {string|undefined} The cookie value if found, otherwise undefined.
   */
  function getCookie(name) {
    const cookies = document.cookie ? document.cookie.split('; ') : [];
    for (let i = 0; i < cookies.length; i++) {
      const [key, ...rest] = cookies[i].split('=');
      if (decodeURIComponent(key) === name) {
        return decodeURIComponent(rest.join('='));
      }
    }
  }

  /**
   * Sets a cookie with the given name and value.
   *
   * @param {string} name - The cookie name.
   * @param {string|number} value - The value to store.
   * @param {Object} [options] - Optional attributes for the cookie.
   * @param {number} [options.expires] - Unix timestamp for expiry. 0 means session cookie.
   * @param {string} [options.path='/'] - Path scope of the cookie.
   * @param {boolean} [options.secure=false] - Transmit over HTTPS only.
   * @param {string} [options.samesite='Lax'] - SameSite policy.
   */
  function setCookie(name, value, options = {}) {
    const path = options.path || '/';
    let cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; path=${path}`;
    if (options.expires) {
      cookie += `; expires=${new Date(options.expires * 1000).toUTCString()}`;
    }
    if (options.secure) {
      cookie += '; secure';
    }
    if (options.samesite) {
      cookie += `; samesite=${options.samesite}`;
    }
    document.cookie = cookie;
  }

  /**
   * Attaches the batch behavior for autologout.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.autologout = {
    attach(context, settings) {
      const [body] = once('autologout-once', 'body');
      if (!body) {
        return;
      }

      let theDialog;
      let currentDialog = null;
      let skipLogoutOnClose = false;

      // Timer to keep track of activity resets.
      let activityResetTimer;

      // Prevent settings being overridden by ajax callbacks by cloning it.
      const localSettings = jQuery.extend(true, {}, settings.autologout);

      // Schedules the next inactivity check, replacing any pending one.
      let inactivityTimer;
      function schedule(fn, delay) {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(fn, delay);
      }

      // Schedules the dialog auto-logout countdown, replacing any pending one.
      let paddingTimer;
      function schedulePadding(fn, delay) {
        clearTimeout(paddingTimer);
        paddingTimer = setTimeout(fn, delay);
      }

      function init() {
        const noDialog = settings.autologout.no_dialog;
        if (settings.activity) {
          refresh();
        }
        else {
          // The user has not been active, ask them if they want to stay logged
          // in and start the logout timer.
          schedulePadding(confirmLogout, localSettings.timeout_padding);
          // While the countdown timer is going, lookup the remaining time. If
          // there is more time remaining (i.e. a user is navigating in another
          // tab), then reset the timer for opening the dialog.
          Drupal.Ajax['autologout.getTimeLeft'].autologoutGetTimeLeft(function (time) {
            if (time > 0) {
              clearTimeout(paddingTimer);
              schedule(init, time);
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

      function triggerLogoutEvent(logoutMethod, logoutUrl) {
        const logoutEvent = new CustomEvent('autologout', {
          detail: {
            logoutMethod,
            logoutUrl,
          },
        });
        document.dispatchEvent(logoutEvent);
      }

      function logout() {
        if (localSettings.use_alt_logout_method) {
          const logoutUrl = localSettings.alt_logout_url;
          triggerLogoutEvent('alternative', logoutUrl);
          window.location = logoutUrl;
        }
        else {
          $.ajax({
            url: localSettings.ajax_logout_url,
            type: "POST",
            beforeSend(xhr) {
              xhr.setRequestHeader('X-Requested-With', {
                toString() {
                  return '';
                },
              });
            },
            success() {
              const logoutUrl = localSettings.redirect_url;
              triggerLogoutEvent('normal', logoutUrl);
              window.location = logoutUrl;
            },
            error(XMLHttpRequest, textStatus) {
              if (XMLHttpRequest.status === 403 || XMLHttpRequest.status === 404) {
                window.location = localSettings.redirect_url;
              }
            },
          });
        }
      }

      function refresh() {
        Drupal.Ajax['autologout.refresh'].autologoutRefresh(init);
      }

      function keepAlive() {
        if (!document.hidden) {
          Drupal.Ajax['autologout.refresh'].autologoutRefresh(keepAlive);
        }
        else {
          schedule(keepAlive, localSettings.timeout);
        }
      }

      // A user could have used the reset button on the tab/window they're
      // actively using, so we need to double check before actually logging out.
      function confirmLogout() {
        if (currentDialog) {
          skipLogoutOnClose = true;
          currentDialog.close();
          currentDialog = null;
        }

        Drupal.Ajax['autologout.getTimeLeft'].autologoutGetTimeLeft(function (time) {
          if (time > 0) {
            schedule(init, time);
          }
          else {
            logout();
          }
        });
      }

      function dialog() {
        const disableButtons = settings.autologout.disable_buttons;
        let stayLoggedIn = false;

        const buttons = {};
        if (!disableButtons) {
          const yesButton = settings.autologout.yes_button;
          buttons[Drupal.t(yesButton)] = function () {
            setCookie('Drupal.visitor.autologout_login', Math.round((new Date()).getTime() / 1000), {
              expires: localSettings.cookie_lifetime,
              secure: localSettings.cookie_secure,
              samesite: localSettings.cookie_samesite,
            });
            clearTimeout(paddingTimer);
            stayLoggedIn = true;
            if (currentDialog) {
              currentDialog.close();
              currentDialog = null;
            }
            refresh();
          };

          const noButton = settings.autologout.no_button;
          buttons[Drupal.t(noButton)] = function () {
            if (currentDialog) {
              currentDialog.close();
              currentDialog = null;
            }
            logout();
          };
        }

        const $modal = $(`<div id="autologout-confirm">${localSettings.message}</div>`);
        const modalSettings = {
          modal: true,
          closeOnEscape: false,
          width: localSettings.modal_width,
          title: localSettings.title,
          buttons,
          close(event, ui) {
            $(event.target).remove();
            currentDialog = null;
            if (!stayLoggedIn && !skipLogoutOnClose) {
              logout();
            }
            skipLogoutOnClose = false;
          },
        };

        // Check if dialog already exists to prevent duplicates.
        if (document.getElementById('autologout-confirm')) {
          return;
        }

        currentDialog = Drupal.dialog($modal.get(0), modalSettings);
        currentDialog.showModal();
        return $modal;
      }

      // Add timer element to prevent detach of all behaviours.
      const timerMarkup = document.createElement('DIV');
      timerMarkup.setAttribute('id', 'timer');
      timerMarkup.style.display = 'none';
      document.body.appendChild(timerMarkup);

      if (localSettings.refresh_only) {
        // On pages where user shouldn't be logged out, don't set the timer.
        schedule(keepAlive, localSettings.timeout);
      }
      else {
        settings.activity = false;
        if (localSettings.logout_regardless_of_activity) {
          // Ignore users activity and set timeout.
          const timestamp = Math.round((new Date()).getTime() / 1000);
          const loginTime = getCookie('Drupal.visitor.autologout_login');
          const difference = (timestamp - loginTime) * 1000;

          schedule(init, Math.max(0, localSettings.timeout - difference));
        }
        else {
          // Bind formUpdated events to preventAutoLogout event.
          document.body.addEventListener('formUpdated', debounce(function () {
            document.body.dispatchEvent(new Event('preventAutologout'));
          }));

          // Bind mousemove events to preventAutoLogout event.
          document.body.addEventListener('mousemove', debounce(function () {
            document.body.dispatchEvent(new Event('preventAutologout'));
          }));

          // Replaces the CKEditor5 check because keyup should always prevent autologout.
          document.body.addEventListener('keyup', debounce(function () {
            document.body.dispatchEvent(new Event('preventAutologout'));
          }));

          document.body.addEventListener('preventAutologout', function () {
            // When the preventAutologout event fires, we set activity to true.
            settings.activity = true;

            // Clear timer if one exists.
            clearTimeout(activityResetTimer);

            // Set a timer that goes off and resets this activity indicator after
            // half a minute, otherwise sessions never timeouts.
            activityResetTimer = setTimeout(function () {
              settings.activity = false;
            }, 30000);
          });

          // On pages where the user should be logged out, set the timer to popup
          // and log them out.
          schedule(init, localSettings.timeout);
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
        const ajax = this;

        // Store the original success temporary to be called later.
        const originalSuccess = ajax.options.success;
        ajax.options.submit = {
          uactive: settings.activity,
        };
        ajax.options.success = function (response, status, xmlhttprequest) {
          if (typeof response === 'string') {
            response = JSON.parse(response);
          }
          if (typeof response[0].command === 'string' && response[0].command === 'alert') {
            // In the event of an error, we can assume user has been logged out.
            window.location = localSettings.redirect_url;
          }

          // Loop through response to get correct keys.
          Object.keys(response).forEach(function (key) {
            if (response[key].command === 'settings' && typeof response[key].settings.time !== 'undefined') {
              callback(response[key].settings.time);
            }
            if (response[key].command === 'insert' && response[key].selector === '#timer' && typeof response[key].data !== 'undefined') {
              response[key].data = `<div id="timer" style="display: none;">${response[key].data}</div>`;
            }
          });

          // Let Drupal.ajax handle the JSON response.
          return originalSuccess.call(ajax, response, status, xmlhttprequest);
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
        url: localSettings.ajax_get_time_left_url,
        submit: {
          uactive: settings.activity,
        },
        event: 'autologout.getTimeLeft',
        error(XMLHttpRequest, textStatus) {
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
        const ajax = this;

        if (ajax.ajaxing) {
          return false;
        }

        // Store the original success temporary to be called later.
        const originalSuccess = ajax.options.success;
        ajax.options.success = function (response, status, xmlhttprequest) {
          if (typeof response === 'string') {
            response = JSON.parse(response);
          }
          if (typeof response[0].command === 'string' && response[0].command === 'alert') {
            // In the event of an error, we can assume the user has been logged out.
            window.location = localSettings.redirect_url;
          }

          schedule(timerfunction, localSettings.timeout);

          // Wrap response data in timer markup to prevent detach of all behaviors.
          response[0].data = `<div id="timer" style="display: none;">${response[0].data}</div>`;

          // Let Drupal.ajax handle the JSON response.
          return originalSuccess.call(ajax, response, status, xmlhttprequest);
        };

        try {
          $.ajax(ajax.options);
        } catch (e) {
          ajax.ajaxing = false;
        }
      };

      Drupal.Ajax['autologout.refresh'] = Drupal.ajax({
        base: null,
        element: document.body,
        url: localSettings.ajax_set_last_url,
        event: 'autologout.refresh',
        error(XMLHttpRequest, textStatus) {
          // Disable error reporting to the screen.
        },
      });

      // Check if the page was loaded via a back button click.
      const dirtyBit = document.getElementById('autologout-cache-check-bit');
      if (dirtyBit) {
        if (dirtyBit.value === '1') {
          // Page was loaded via back button click, we should refresh the timer.
          refresh();
        }
        dirtyBit.value = '1';
      }
    },
  };
})(jQuery, Drupal, once);
