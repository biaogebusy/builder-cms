/**
 * @file
 * JavaScript behaviors to fix jQuery UI dialogs.
 */

(function ($, Drupal, once, tabbable) {
  /**
   * Ensure that ckeditor has focus when displayed inside of jquery-ui dialog widget
   *
   * @see http://stackoverflow.com/questions/20533487/how-to-ensure-that-ckeditor-has-focus-when-displayed-inside-of-jquery-ui-dialog
   */
  if ($.ui && $.ui.dialog && $.ui.dialog.prototype._allowInteraction) {
    var _allowInteraction = $.ui.dialog.prototype._allowInteraction;
    $.ui.dialog.prototype._allowInteraction = function (event) {
      if ($(event.target).closest('.cke_dialog').length) {
        return true;
      }
      return _allowInteraction.apply(this, arguments);
    };
  }

  /**
   * Attaches webform dialog behaviors.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches event listeners for webform dialogs.
   */
  Drupal.behaviors.webformDialogEvents = {
    attach: function () {
      if (once('webform-dialog', 'html').length) {
        $(window).on({
          'dialog:aftercreate': function (event, dialog, $element, settings) {
            setTimeout(function () {
              const tabbableElements = tabbable.tabbable($element.get(0));
              var hasFocus = $element.find("[autofocus]");
              if (!hasFocus.length) {
                // Move focus to first input which is not a button.
                hasFocus = tabbableElements.filter((element) => {
                  // Check if the element is an <input> tag and not a <button> tag
                  return (
                    element.tagName.toLowerCase() === "input" &&
                    element.type !== "button"
                  );
                });
                hasFocus = $(hasFocus);
              }
              if (!hasFocus.length) {
                // Move focus to close dialog button.
                hasFocus = $element.parent().find('.ui-dialog-titlebar-close');
              }
              hasFocus.eq(0).trigger('focus');
            });
          }
        });
      }
    }
  };

})(jQuery, Drupal, once, tabbable);
