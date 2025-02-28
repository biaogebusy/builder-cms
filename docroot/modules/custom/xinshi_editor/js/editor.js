(function ($, Drupal, debounce, JSONEditor) {
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
  if (Drupal.editors) Drupal.editors.json_editor = {
    attach: function (element, format) {
      // Identifying the textarea as jQuery object.
      var $element = $(element);
      var element_id = $element.attr("id");

      // Creating a unique id for our new text editor
      var json_editor_id = element_id + "-json-editor";

      // We don't delete the original textarea, but hide it.
      $element.hide().css('visibility', 'hidden');

      // We introduce a dummy dom element to make our editor and attach inside form textarea wrapper.
      var height = format.editorSettings.height ? format.editorSettings.height : '600px';
      var editor_dummy = "<pre style='height:" + height + ";' id='" + json_editor_id + "'></pre>";
      $element.closest(".form-textarea-wrapper").append(editor_dummy);
      var container = document.getElementById(json_editor_id);

      var options = {
        mode: format.editorSettings.mode,
        modes: format.editorSettings.allow_modes,
        onError: function (err) {
          alert(err.toString());
        },
        onChangeText: function (text) {
          $element.val(text);
          $element.change();
        }
      };

      var current_editor = editors[json_editor_id] = new JSONEditor(container, options, {});
      return !!current_editor;

    },
    detach: function (element, format, trigger) {
      // Identifying textarea as a jQuery object.
      var $element = $(element);
      var element_id = $element.attr("id");
      var json_editor_id = element_id + "-json-editor";
      var current_editor = editors[json_editor_id];

      // Copy value to element textarea.
      //$element.val(editors[json_editor_id].getSession().getValue());
      if (trigger === 'serialize') {
      } else {
        editors[json_editor_id].destroy();
        editors[json_editor_id].container.remove();

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
      var json_editor_id = element_id + "-json-editor";
      var current_editor = editors[json_editor_id];

      // On attaching our json_editor, get value from textarea.
      editors[json_editor_id].setText($element.val());
      return !!current_editor;

    }
  };

})(jQuery, Drupal, Drupal.debounce, JSONEditor);
