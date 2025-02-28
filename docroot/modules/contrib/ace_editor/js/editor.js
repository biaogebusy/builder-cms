(function ($, Drupal, debounce, ace) {
    'use strict';
    // A page may contain multiple editors. editors variable store all of them as { id: editor_object }
    var editors = {};
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

            // We don't delete the original textarea, but hide it.
            $element.hide().css('visibility', 'hidden');

            // We introduce a dummy dom element to make our editor and attach inside form textarea wrapper.
            var editor_dummy = "<pre id='"+ace_editor_id+"'></pre>";
            $element.closest(".form-textarea-wrapper").append(editor_dummy);

            // Creating new editor, setting syntax and theme.
            var current_editor = editors[ace_editor_id] = ace.edit(ace_editor_id);
            var theme = format.editorSettings["theme"];
            var mode = format.editorSettings["syntax"];
            editors[ace_editor_id].setTheme("ace/theme/"+theme);
            editors[ace_editor_id].getSession().setMode("ace/mode/"+mode);

            // Setting ace_editor styles.
            $("#"+ace_editor_id).height(format.editorSettings.height).width(format.editorSettings.width);
            editors[ace_editor_id].setOptions({
                fontSize: format.editorSettings.font_size ? format.editorSettings.font_size : '12pt',
                showLineNumbers: format.editorSettings.line_numbers ? true : false,
                showPrintMargin: format.editorSettings.print_margin ? true: false,
                showInvisibles: format.editorSettings.show_invisibles ? true: false,
                enableBasicAutocompletion: format.editorSettings.auto_complete ? true: false
            });

            if (format.editorSettings.use_wrap_mode) {
              editors[ace_editor_id].getSession().setUseWrapMode(true);
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

                $element.show().css('visibility', 'visible');
                //element.removeAttribute('contentEditable');
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
