# Library Detection

The Ace library is located at runtime by `ace_editor_lib_path()` in
`ace_editor.module`. It checks the following locations, in order, relative to the
Drupal root:

1. `/libraries/ace`
2. `/libraries/ace-builds`
3. `<module path>/libraries`
4. `<install profile path>/libraries/ace` (only when a profile is active)

For each existing directory it scans **recursively** for a file named `ace.js`
and returns the path of the first match (with the `ace.js` filename stripped).
This means a nested build such as
`/libraries/ace/src-min-noconflict/ace.js` resolves to
`/libraries/ace/src-min-noconflict/`.

If no `ace.js` is found, the function returns `FALSE`; the module then surfaces a
warning on install and an error on the status report (see
`ace_editor_requirements()`).

## Building the theme/mode libraries

Once the base path is known, `hook_library_info_build()` scans that directory
(non-recursively) for files matching `theme-*.js` / `mode-*.js` and registers a
Drupal library for each:

- `theme-cobalt.js` → `ace_editor/theme.cobalt`
- `mode-html.js` → `ace_editor/mode.html`

`hook_library_info_alter()` then prepends the resolved `ace.js` (weight `-2`) to
the `primary`, `formatter` and `filter` libraries, and adds
`ext-language_tools.js` to `primary` when autocomplete is enabled in
configuration.

## Consequences for packaging

Because detection is path-based and recursive, you can supply the library via:

- a manual download into `/libraries/ace` (the `src-min-noconflict` build), or
- a Composer-managed asset (`npm-asset/ace-builds` via asset-packagist), or
- the module's own `libraries/` directory.

No configuration is needed — the first `ace.js` found wins.
