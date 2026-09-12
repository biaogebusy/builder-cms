/**
 * @file entity_browser.modal.js
 *
 * Defines the behavior of the entity browser's modal display.
 */

(function ($, Drupal, drupalSettings, window, document) {

  'use strict';

  Drupal.entityBrowserModal = {};

  Drupal.AjaxCommands.prototype.select_entities = function (ajax, response, status) {
    var uuid = drupalSettings.entity_browser.modal.uuid;

    // 'entities-selected' is a jQuery event, keep jQuery to trigger it.
    $(':input[data-uuid="' + uuid + '"]').trigger('entities-selected', [uuid, response.entities])
      .removeClass('entity-browser-processed').off('entities-selected');
  };

  /**
   * Registers behaviours related to modal display.
   */
  Drupal.behaviors.entityBrowserModal = {
    attach: function (context) {
      for (var modalId in drupalSettings.entity_browser.modal) {
        var instance = drupalSettings.entity_browser.modal[modalId];
        // The uuid is unique, so there is only one button.
        var button = context.querySelector('[data-uuid="' + instance.uuid + '"]');

        if (button && !button.classList.contains('entity-browser-processed')) {
          for (var jsCallbackKey in instance.js_callbacks) {
            var callback = instance.js_callbacks[jsCallbackKey];
            // Get the callback.
            callback = callback.split('.');
            var fn = window;

            for (var j = 0; j < callback.length; j++) {
              fn = fn[callback[j]];
            }

            if (typeof fn === 'function') {
              // 'entities-selected' is a jQuery event, bind it with jQuery.
              $(button).on('entities-selected', fn);
            }
          }
          if (instance.auto_open) {
            button.focus();
            button.click();
          }
          button.classList.add('entity-browser-processed');
        }
      }
    }
  };

  /**
   * Registers behaviours related to modal open and windows resize for fluid modal.
   */
  Drupal.behaviors.fluidModal = {
    attach: function (context) {
      // Be sure to run only once per window document.
      if (once('fluid-modal', 'body').length === 0) {
        return;
      }

      // Recalculate dialog size on window resize.
      window.addEventListener('resize', function () {
        Drupal.entityBrowserModal.fluidDialog();
      });

      // Resize open dialogs and lock the body scroll when a dialog opens.
      // Core resizes dialogs on dialog:aftercreate too.
      window.addEventListener('dialog:aftercreate', function () {
        Drupal.entityBrowserModal.fluidDialog();
        document.body.style.overflow = 'hidden';
      });
      window.addEventListener('dialog:beforeclose', function () {
        document.body.style.overflow = 'inherit';
      });
    }
  };

  /**
   * Registers behaviours for adding throbber on modal open.
   */
  Drupal.behaviors.entityBrowserAddThrobber = {
    attach: function (context) {
      if (context === document) {
        window.addEventListener('dialog:aftercreate', function (event) {
          var element = event.target;
          if (element.querySelector('iframe.entity-browser-modal-iframe')) {
            element.insertAdjacentHTML('beforeend', Drupal.theme('ajaxProgressThrobber'));
          }
        });
      }
    }
  };

  /**
   * Recalculates size of the modal.
   */
  Drupal.entityBrowserModal.fluidDialog = function () {

    // These are jQuery UI dialog widgets, so this stays on jQuery.
    var $visible = $('.ui-dialog:visible');
    // For each open dialog.
    $visible.each(function () {
      var $this = $(this);
      var dialog = $this.find('.ui-dialog-content').data('ui-dialog');
      // If fluid option == true.
      if (dialog && dialog.options.fluid) {
        // Check window width against dialog width.
        if (dialog.options.maxWidth && (window.innerWidth > parseInt(dialog.options.maxWidth) + 50)) {
          dialog.option('width', dialog.options.maxWidth);
        }
        else {
          // If no maxWidth is defined, make it responsive.
          dialog.option('width', '92%');
        }

        // Check window width against dialog width.
        if (dialog.options.maxHeight && window.innerHeight > (parseInt(dialog.options.maxHeight) + 50)) {
          dialog.option('height', dialog.options.maxHeight);
        }
        else {
          // If no maxHeight is defined, make it responsive.
          dialog.option('height', .92 * window.innerHeight);

          // Because there is no iframe height 100% in HTML 5, we have to set
          // the height of the iframe as well.
          var contentHeight = $this.find('.ui-dialog-content').height();
          var iframe = $this.find('iframe').get(0);
          if (iframe) {
            iframe.style.height = contentHeight + 'px';
          }
        }

        // Reposition dialog.
        dialog.option('position', dialog.options.position);
      }
    });

    /**
     * Close modal popup on escape key press.
     */
    Drupal.behaviors.closeModalOnEscapeKeyPress = {
      attach: function (context) {
        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') {
            var iframe = document.querySelector('.entity-browser-modal-iframe');
            var dialog = iframe ? iframe.closest('.ui-dialog') : null;
            var closeButton = dialog ? dialog.querySelector('.ui-dialog-titlebar-close') : null;
            if (closeButton) {
              closeButton.click();
            }
          }
        });
      }
    };
  };

}(jQuery, Drupal, drupalSettings, window, document));
