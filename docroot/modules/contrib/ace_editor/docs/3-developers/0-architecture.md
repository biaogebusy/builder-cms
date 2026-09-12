# Architecture

Ace Editor is a thin Drupal integration layer over the upstream Ace library. It
ships three plugins and three matching JavaScript behaviours.

## Plugins

| Surface | Plugin | Class |
| --- | --- | --- |
| Text editor | `@Editor("ace_editor")` | `src/Plugin/Editor/AceEditor.php` |
| Field formatter | `@FieldFormatter("ace_formatter")` | `src/Plugin/Field/FieldFormatter/AceFormatter.php` |
| Text filter | `@Filter("ace_filter")` | `src/Plugin/Filter/AceFilter.php` |

- **AceEditor** (extends `EditorBase`) builds the settings form, returns the
  per-format settings to JavaScript via `getJsSettings()`, and resolves the
  `theme.<name>` / `mode.<name>` libraries to attach via `getLibraries()`.
- **AceFormatter** (extends `FormatterBase`) renders each field value as a
  read-only `<textarea class="content">` wrapped in `.ace_formatter` and attaches
  the `ace_editor/formatter` library plus the settings in `drupalSettings`.
- **AceFilter** (extends `FilterBase`) extracts every `<ace> … </ace>` block,
  replaces it with `<pre id="ace-editor-inline…">`, and passes the snippet
  content + per-tag settings to JavaScript in
  `drupalSettings.ace_filter.instances`.

## JavaScript

| File | Registers | Role |
| --- | --- | --- |
| `js/editor.js` | `Drupal.editors.ace_editor` | Attach/detach Ace to a textarea on edit forms; sync edits back to the hidden textarea. |
| `js/formatter.js` | `Drupal.behaviors.ace_formatter` | Turn each `.ace_formatter` field into a read-only editor. |
| `js/filter.js` | `Drupal.behaviors.ace_filter` | Turn each `<ace>` snippet into a read-only editor, applying per-tag overrides. |

The formatter and filter use **distinct** behaviour names so both can run on the
same page without clobbering each other.

## Libraries

`ace_editor.libraries.yml` declares `primary`, `formatter` and `filter`
libraries. `hook_library_info_alter()` injects the resolved path to `ace.js`
(and `ext-language_tools.js` when autocomplete is enabled), while
`hook_library_info_build()` auto-registers a `theme.<name>` / `mode.<name>`
library for every `theme-*.js` / `mode-*.js` file found in the Ace library
directory.

See [Library Detection](1-library-detection.md) for how the library path is
resolved.
