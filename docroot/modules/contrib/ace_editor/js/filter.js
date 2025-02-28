(function ($, Drupal) {
    'use strict';

    Drupal.behaviors.ace_formatter = {
        attach: function (context, settings) {

            // Gettings ace_formatter settings from settings variable.
            var ace_settings = settings.ace_filter.theme_settings;
            var instances = settings.ace_filter.instances;

            $.each(instances,function(){
                // Getting container as jQuery object.
                var id = this.id;

                // Selecting the content
                var content = this.content;
                var custom_ace_settings = ace_settings;
                jQuery.extend(custom_ace_settings,this.settings);

                // Setting theme and mode variable.
                var theme = ace_settings.theme;
                var mode = ace_settings.syntax;


                // Setting editor style and properties.
                var editor = ace.edit(id);
                editor.setReadOnly(true);
                editor.setTheme("ace/theme/"+theme);
                editor.getSession().setMode("ace/mode/"+mode);
                editor.getSession().setValue(content);
                $("#"+id).height(ace_settings.height).width(ace_settings.width);

                editor.setOptions({
                    fontSize: ace_settings.font_size ? ace_settings.font_size : '12pt',
                    showLineNumbers: !!ace_settings.line_numbers,
                    showPrintMargin: !!ace_settings.print_margin,
                    showInvisibles: !!ace_settings.show_invisibles
                });
            })
        }
    };

})(jQuery, Drupal);