(function (Drupal, window) {

  'use strict';

  Drupal.behaviors.suggestionsFieldWidgetActions = {
    attach: function (context, settings) {
      const suggestions = context.querySelectorAll('.fwa-use-suggestion');
      if (suggestions.length === 0) {
        return;
      }
      suggestions.forEach(function (suggestion) {
        suggestion.addEventListener('click', function (event) {
          event.target.classList.toggle('active');
          setTimeout(function() {
            const text = event.target.parentElement.innerText;
            const target = document.querySelector('[data-drupal-selector="' + settings.fwa_suggestion_target.target + '"]');
            if (target) {
              if (target.classList.contains('form-textarea')) {
                const domEditableElement = target.parentElement.querySelector('.ck-editor__editable')
                if (domEditableElement) {
                  // Get the editor instance from the editable element.
                  const editorInstance = domEditableElement.ckeditorInstance;
                  editorInstance.setData(text);
                }
                else {
                  target.value = text;
                }
              }
              else {
                target.value = text;
              }
            }
            document.querySelector(".ui-dialog-titlebar-close").click();
          }, 300);
        });
        suggestion.addEventListener('mouseover', function (event) {
          if (!event.target.hasAttribute('title')) {
            event.target.setAttribute('title', Drupal.t('Use suggestion'));
          }
        });
      });
    }
  };

  window.addEventListener('dialog:beforecreate', (e) => {
    let settings = e.settings;
    // Your logic here
    settings.buttons.forEach(function (setting, index) {
      if (setting.click) {
        settings.buttons[index].click = new Function(`return ${setting.click}`)();
      }
    });
    e.settings = settings;
  });

})(Drupal, window);
