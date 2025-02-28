(function ($, Drupal) {
    'use strict';

    Drupal.behaviors.ace_formatter = {
        attach: function (context, settings) {

            // Gettings ace_formatter settings from settings variable.
            var ace_settings = settings.ace_formatter;

            // Selecting all the containers.
            var ace_format_containers = $(".ace_formatter");

            // Looping through each container and setting read only editor.
            $.each(ace_format_containers,function(index,container){

                // Getting container as jQuery object.
                container = $(container);

                // setting unique id for the editor.
                var display_id = 'ace_formatter_display_' + index;
                if (!container.children("#" + display_id).length){
                    // This script is found loading multiple times. So adding dummy div for editor if not loaded earlier.
                    container.append("<div id='" + display_id + "'></div>");
                }

                // Selecting the content.
                var content = container.find(".content:first");
                // Content is hided insted of deleted.
                content.hide();

                // Setting theme and mode variable.
                var theme = ace_settings.theme;
                var mode = ace_settings.syntax;

                // Setting editor style and properties.
                var editor = ace.edit(display_id);
                editor.setReadOnly(true);
                editor.setTheme("ace/theme/"+theme);
                editor.getSession().setMode("ace/mode/"+mode);
                editor.getSession().setValue(content.val());
                $("#"+display_id).height(ace_settings.height).width(ace_settings.width);

                editor.setOptions({
                    fontSize: ace_settings.font_size ? ace_settings.font_size : '12pt',
                    showLineNumbers: !!ace_settings.line_numbers,
                    showPrintMargin: !!ace_settings.print_margin,
                    showInvisibles: !!ace_settings.show_invisibles
                });

            });
        }
    };

})(jQuery, Drupal);