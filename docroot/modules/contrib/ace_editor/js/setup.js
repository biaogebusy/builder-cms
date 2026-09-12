(function (drupalSettings, ace) {
    'use strict';

    /**
     * @file
     * Points Ace at the directory its library was loaded from.
     *
     * Ace resolves the mode, theme and worker files it loads on demand against
     * the URL of its own script. That URL is not always the library directory:
     * an aggregate, or an asset served from elsewhere, leaves Ace requesting
     * those files from the wrong place. The module publishes the directory in
     * drupalSettings, so the path is explicit (issue #3326303).
     */
    if (!ace || !ace.config || !drupalSettings.ace_editor || !drupalSettings.ace_editor.base_path) {
        return;
    }

    var base = drupalSettings.ace_editor.base_path.replace(/\/$/, '');

    ace.config.set('basePath', base);
    ace.config.set('modePath', base);
    ace.config.set('themePath', base);
    ace.config.set('workerPath', base);

})(drupalSettings, window.ace);
