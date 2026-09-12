(function ($, Drupal, debounce, ace) {
    'use strict';
    // A page may contain multiple editors. editors variable store all of them as { id: editor_object }
    var editors = {};
    // Store required elements to restore them later
    var requiredElements = new Set();
    /**
     * @file
     * Defines AceEditor as a Drupal editor.
     */

    /**
     * Define editor methods.
     */
    if (Drupal.editors) Drupal.editors.ace_editor = {
        attach: function (element, format) {
            // Identifying the textarea as jQuery object.
            var $element = $(element);
            var element_id = $element.attr("id");

            // Creating a unique id for our new text editor
            var ace_editor_id = element_id+"-ace-editor";
            // Handle the 'required' attribute
            if (element.hasAttribute('required')) {
              // Store the ID of the required element
              requiredElements.add(element_id);
              // Remove the 'required' attribute
              element.removeAttribute('required');
            }
            // We don't delete the original textarea, but hide it.
            $element.hide().css('visibility', 'hidden');

            // We introduce a dummy dom element to make our editor and attach inside form textarea wrapper.
            var editor_dummy = "<pre id='"+ace_editor_id+"'></pre>";
            $element.closest(".js-form-type-textarea").append(editor_dummy);

            // Creating new editor, setting syntax and theme.
            var current_editor = editors[ace_editor_id] = ace.edit(ace_editor_id);
            // Core delivers the format over AJAX when the text format select
            // changes, and a text format whose stored settings are empty
            // arrives with editorSettings set to null. Fall back to defaults
            // so the editor still attaches instead of throwing.
            var settings = format.editorSettings || {};
            var theme = settings["theme"] || "chrome";
            var mode = settings["syntax"] || "html";
            editors[ace_editor_id].setTheme("ace/theme/"+theme);
            editors[ace_editor_id].getSession().setMode("ace/mode/"+mode);

            // Setting ace_editor styles.
            $("#"+ace_editor_id).height(settings.height || "300px").width(settings.width || "100%");
            // The configuration key is `print_margins`; older inline <ace> tags
            // use the `print-margin` attribute (normalised to `print_margin`).
            // Honour whichever is present so both paths show the print margin.
            var showPrintMargin = settings.print_margins;
            if (showPrintMargin === undefined) {
              showPrintMargin = settings.print_margin;
            }
            editors[ace_editor_id].setOptions({
                fontSize: settings.font_size ? settings.font_size : '12pt',
                showLineNumbers: settings.line_numbers ? true : false,
                showPrintMargin: showPrintMargin ? true: false,
                showInvisibles: settings.show_invisibles ? true: false,
                enableBasicAutocompletion: settings.auto_complete ? true: false
            });

            // Free the Tab key and add the Esc escape, so a keyboard user is
            // not trapped in the editor (WCAG 2.1.2). Older Ace builds do not
            // know the option; setting it is harmless there.
            try {
                editors[ace_editor_id].setOption('enableKeyboardAccessibility', true);
            } catch (e) {}

            // Carry the field's label to the editor, and mark it required when
            // the original textarea was, so assistive technology names and
            // announces the replacement the same way.
            try {
                var labelText = $("label[for='" + element_id + "']").first().text().trim();
                if (labelText) {
                    editors[ace_editor_id].setOption('textInputAriaLabel', labelText);
                }
                if (requiredElements.has(element_id)) {
                    editors[ace_editor_id].textInput.getElement().setAttribute('aria-required', 'true');
                }
            } catch (e) {}

            if (settings.use_wrap_mode) {
              editors[ace_editor_id].getSession().setUseWrapMode(true);
            }

            // A form can disable a field, and Drupal renders that as a
            // disabled or readonly textarea. Ace hides the textarea and takes
            // over the editing, so without this the field stays editable
            // through the editor (issue #3046914).
            if (element.disabled || element.readOnly) {
              current_editor.setReadOnly(true);
            }

            return !!current_editor;
            
        },
        detach: function (element, format, trigger) {
            // Identifying textarea as a jQuery object.
            var $element = $(element);
            var element_id = $element.attr("id");
            var ace_editor_id = element_id+"-ace-editor";
            var current_editor = editors[ace_editor_id];

            // Copy value to element textarea.
            //$element.val(editors[ace_editor_id].getSession().getValue());
            if (trigger === 'serialize') {
            }
            else{
                editors[ace_editor_id].destroy();
                editors[ace_editor_id].container.remove();
                // Restore the 'required' attribute if it was originally present
                if (requiredElements.has(element_id)) {
                  element.setAttribute('required', 'required');
                  requiredElements.delete(element_id); // Remove from the set
                }
                $element.show().css('visibility', 'visible');
                //element.removeAttribute('contentEditable');
                // Drop the destroyed editor from the registry, so it is not
                // held in memory for the life of the page.
                delete editors[ace_editor_id];
            }
            return !!current_editor;

        },
        onChange: function (element, callback) {
            // Identifying the textarea as jQuery object.
            var $element = $(element);
            var element_id = $element.attr("id");

            // Creating a unique id for our new text editor
            var ace_editor_id = element_id+"-ace-editor";
            var current_editor = editors[ace_editor_id];

            // On attaching our ace_editor, get value from textarea.
            editors[ace_editor_id].getSession().setValue($element.val());

            // On each change in ace_editor, change hidden textarea value and change attribute to show it is edited.
            editors[ace_editor_id].getSession().on('change', debounce(function () {
                $element.val(editors[ace_editor_id].getSession().getValue());
                callback();
            }, 400));

            return !!current_editor;

        }
    };

})(jQuery, Drupal, Drupal.debounce, ace);
