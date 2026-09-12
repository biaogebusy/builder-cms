(function ($, Drupal, once, ace) {
    'use strict';

    /**
     * @file
     * Turns a field widget textarea into an editable Ace editor.
     *
     * The text editor integration only reaches fields that have a text format.
     * This works on any field configured with the "Ace Editor" widget, reading
     * the settings of each instance from its own data attribute (issue
     * #2933546).
     */

    /** Reads the settings of one textarea. */
    function widgetSettings(element) {
        var raw = element.getAttribute('data-ace-widget-settings');
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        }
        catch (e) {
            return null;
        }
    }

    Drupal.behaviors.ace_widget = {
        attach: function (context) {

            once('ace-widget', 'textarea.ace-editor-widget', context).forEach(function (element) {

                var $element = $(element);
                var settings = widgetSettings(element);
                if (!settings) {
                    return;
                }

                // The textarea stays in the DOM, hidden, so the form still
                // submits its value.
                $element.hide();

                var editor_id = element.id + '-ace-widget';
                $element.after("<pre id='" + editor_id + "'></pre>");
                $("#" + editor_id).width(settings.width || '100%');

                var editor = ace.edit(editor_id);
                if (settings.theme) {
                    editor.setTheme("ace/theme/" + settings.theme);
                }
                if (settings.syntax) {
                    editor.getSession().setMode("ace/mode/" + settings.syntax);
                }
                editor.getSession().setValue($element.val());

                // A height of "auto" grows the editor to fit its content
                // through Ace's own line sizing (issue #2846046). Setting it as
                // a CSS height instead collapses the editor.
                if (settings.height === 'auto') {
                    editor.setOption('maxLines', Infinity);
                }
                else {
                    $("#" + editor_id).height(settings.height || '300px');
                }

                editor.setOptions({
                    fontSize: settings.font_size ? settings.font_size : '12pt',
                    showLineNumbers: !!settings.line_numbers,
                    showPrintMargin: !!settings.print_margins,
                    showInvisibles: !!settings.show_invisibles,
                    enableBasicAutocompletion: !!settings.auto_complete
                });

                // Free Tab and add the Esc escape so a keyboard user is not
                // trapped in the editor (WCAG 2.1.2).
                try {
                    editor.setOption('enableKeyboardAccessibility', true);
                    var labelText = $("label[for='" + element.id + "']").first().text().trim();
                    if (labelText) {
                        editor.setOption('textInputAriaLabel', labelText);
                    }
                } catch (e) {}

                if (settings.use_wrap_mode) {
                    editor.getSession().setUseWrapMode(true);
                }

                // A disabled or readonly field must stay uneditable through the
                // editor as well (issue #3046914).
                if (element.disabled || element.readOnly) {
                    editor.setReadOnly(true);
                }

                // Keep the hidden textarea in step so the form submits what the
                // editor holds.
                editor.getSession().on('change', function () {
                    $element.val(editor.getSession().getValue());
                });
            });
        }
    };

})(jQuery, Drupal, once, ace);
