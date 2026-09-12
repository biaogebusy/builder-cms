(function ($, Drupal) {
    'use strict';

    // Use a behaviour name distinct from the field formatter (ace_formatter),
    // otherwise whichever script loads last overwrites the other on pages that
    // render both an Ace-formatted field and a filtered <ace> snippet.
    Drupal.behaviors.ace_filter = {
        attach: function (context, settings) {

            if (!settings.ace_filter || !settings.ace_filter.instances) {
                return;
            }

            // Default theme settings shared by every <ace> snippet on the page.
            var theme_settings = settings.ace_filter.theme_settings;
            var instances = settings.ace_filter.instances;

            $.each(instances,function(){
                // Getting container as jQuery object.
                var id = this.id;

                // Selecting the content
                var content = this.content;

                // Merge the per-tag attribute overrides on top of the shared
                // defaults without mutating the shared object.
                var custom_ace_settings = $.extend({}, theme_settings, this.settings);

                // Setting theme and mode variable.
                var theme = custom_ace_settings.theme;
                var mode = custom_ace_settings.syntax;

                // Setting editor style and properties.
                var editor = ace.edit(id);
                editor.setReadOnly(true);
                editor.setTheme("ace/theme/"+theme);
                editor.getSession().setMode("ace/mode/"+mode);
                editor.getSession().setValue(content);
                // A height of "auto" grows the editor to fit its content
                // through Ace's own line sizing (issue #2846046).
                if (custom_ace_settings.height === 'auto') {
                    editor.setOption('maxLines', Math.max(editor.getSession().getLength(), 1));
                    $("#"+id).width(custom_ace_settings.width);
                }
                else {
                    $("#"+id).height(custom_ace_settings.height).width(custom_ace_settings.width);
                }

                editor.setOptions({
                    fontSize: custom_ace_settings.font_size ? custom_ace_settings.font_size : '12pt',
                    showLineNumbers: !!custom_ace_settings.line_numbers,
                    showPrintMargin: !!(custom_ace_settings.print_margins !== undefined ? custom_ace_settings.print_margins : custom_ace_settings.print_margin),
                    showInvisibles: !!custom_ace_settings.show_invisibles
                });

                if (custom_ace_settings.use_wrap_mode) {
                    editor.getSession().setUseWrapMode(true);
                }
            })
        }
    };

})(jQuery, Drupal);