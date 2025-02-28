(function ($, Drupal, drupalSettings) {
    'use strict';

    let editor = new JSONEditor(document.getElementById("jsoneditor"));

    Drupal.behaviors.jsonEditor = {
        attach: function (context, settings){

            $(context).find('#jsoneditor').once('#jsoneditor').each(function () {
                editor.set(settings.json_editor);
            });
        }
    };

    Drupal.behaviors.jsonEditorDownload = {
        attach: function (context, settings){
            $(context).find('#jsoneditordownload').each(function () {
                $(this).on('click', function () {
                    // Save Dialog
                    var fname = window.prompt("Save as...");

                    // Check json extension in file name
                    if(fname.indexOf(".")==-1){
                        fname = fname + ".json";
                    }else{
                        if(fname.split('.').pop().toLowerCase() == "json"){
                            // Nothing to do
                        }else{
                            fname = fname.split('.')[0] + ".json";
                        }
                    }
                    var blob = new Blob([editor.getText()], {type: 'application/json;charset=utf-8'});
                    saveAs(blob, fname);
                });
            });
        }
    };
})(jQuery, Drupal, drupalSettings);