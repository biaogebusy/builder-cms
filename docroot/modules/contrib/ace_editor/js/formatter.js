(function ($, Drupal, once) {
    'use strict';

    /**
     * @file
     * Turns every Ace Format field into a read-only Ace editor.
     *
     * Each container carries its own settings in a data attribute, so several
     * fields - and several entities - can be shown on one page with different
     * themes and syntaxes (issue #2999328).
     */

    // A page-wide counter for the injected display ids. once() only returns the
    // containers new to each attach, so a per-call index restarts at 0 and
    // collides with an id from an earlier attach (for example one added over
    // AJAX). This never repeats.
    var displayCount = 0;

    /** Reads the settings of one container. */
    function containerSettings(element, settings) {
        var raw = element.getAttribute('data-ace-formatter-settings');
        if (raw) {
            try {
                return JSON.parse(raw);
            }
            catch (e) {
                // Fall through to the legacy source below.
            }
        }
        // Markup rendered by an older release carried the settings globally.
        // Only use that when it really is a settings object, so a missing
        // attribute can never hand Ace an "undefined" theme.
        var legacy = settings && settings.ace_formatter;
        return (legacy && legacy.theme) ? legacy : null;
    }

    /**
     * Resolves the syntax mode of one editor.
     *
     * With "modelist" enabled the value of the configured syntax field is
     * treated as a file name, so "example.twig" selects the Twig mode.
     */
    function resolveMode(ace_settings) {
        var value = ace_settings.syntax_field_value;
        if (value && ace_settings.modelist && ace.require) {
            try {
                var modelist = ace.require('ace/ext/modelist');
                if (modelist) {
                    var mode = modelist.getModeForPath('.' + String(value).replace(/^\./, ''));
                    if (mode && mode.name) {
                        return mode.name;
                    }
                }
            }
            catch (e) {
                // The modelist extension is not in every Ace build; fall back
                // to the configured syntax below.
            }
        }
        return value || ace_settings.syntax;
    }

    Drupal.behaviors.ace_formatter = {
        attach: function (context, settings) {

            var containers = once('ace-formatter', '.ace_formatter', context);

            containers.forEach(function (element) {

                var container = $(element);
                var ace_settings = containerSettings(element, settings);
                if (!ace_settings) {
                    return;
                }

                // Setting a unique id for the editor within this container.
                var display_id = 'ace_formatter_display_' + (displayCount++);
                var display = container.children('[id^="ace_formatter_display_"]').first();
                if (!display.length) {
                    container.append("<div id='" + display_id + "'></div>");
                }
                else {
                    display_id = display.attr('id');
                }

                // Selecting the content.
                var content = container.find(".content:first");
                // Content is hided insted of deleted.
                content.hide();

                // Setting theme and mode variable.
                var theme = ace_settings.theme;
                var mode = resolveMode(ace_settings);

                // Setting editor style and properties.
                var editor = ace.edit(display_id);
                editor.setReadOnly(true);
                if (theme) {
                    editor.setTheme("ace/theme/" + theme);
                }
                if (mode) {
                    editor.getSession().setMode({
                        path: "ace/mode/" + mode,
                        inline: !!ace_settings.inline
                    });
                }
                editor.getSession().setValue(content.val());
                // A height of "auto" grows the editor to fit its content
                // through Ace's own line sizing (issue #2846046).
                if (ace_settings.height === 'auto') {
                    editor.setOption('maxLines', Math.max(editor.getSession().getLength(), 1));
                    $("#" + display_id).width(ace_settings.width || '100%');
                }
                else {
                    $("#" + display_id).height(ace_settings.height || '300px').width(ace_settings.width || '100%');
                }

                editor.setOptions({
                    fontSize: ace_settings.font_size ? ace_settings.font_size : '12pt',
                    showLineNumbers: !!ace_settings.line_numbers,
                    showPrintMargin: !!(ace_settings.print_margins !== undefined ? ace_settings.print_margins : ace_settings.print_margin),
                    showInvisibles: !!ace_settings.show_invisibles
                });

                if (ace_settings.use_wrap_mode) {
                    editor.getSession().setUseWrapMode(true);
                }

            });
        }
    };

})(jQuery, Drupal, once);
