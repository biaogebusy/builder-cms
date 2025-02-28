(function ($, Drupal, JSONEditor) {
  'use strict';
  Drupal.behaviors.jsonEditor = {
    attach: function (context, settings) {
      $('.js-json-formatter', context).each(function () {
        var text = $(this).data('json');
        var options = {
          mode: "view",
          modes: ["view", "code"],
          onEditable: function (){
            return false;
          }
        };
        $(this).html('');
        var current_editor = new JSONEditor(this, options, {});
        if(typeof text == 'object'){
          current_editor.set(text);
        }else {
          current_editor.setText(text);
        }
      });
    }
  };
})(jQuery, Drupal, JSONEditor);
