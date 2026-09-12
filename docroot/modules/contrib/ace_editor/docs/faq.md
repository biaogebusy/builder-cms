# Frequently Asked Questions

## The editor doesn't appear / I see a plain textarea

The Ace JavaScript library is most likely missing. Ace Editor does **not** bundle
the library; download it and place it under `/libraries`. See
[Installation and Setup](1-users/0-installation.md). Check
**Reports → Status report** — the module reports whether the library was found.

## Where exactly do I put the library?

Anywhere the module scans:

- `/libraries/ace`
- `/libraries/ace-builds`
- the module's own `libraries/` directory

The module recursively looks for `ace.js` and uses the first match, so
`web/libraries/ace/src-min-noconflict/ace.js` works out of the box. See
[Library Detection](3-developers/1-library-detection.md).

## Which version of the library do I need?

Any recent `ace-builds` release (the `src-min-noconflict` build). The CI suite is
pinned to a known-good tag, but newer releases work as well.

## My `<ace>` snippet shows as plain text on the page

Three things must be true:

1. The **Ace Filter** is enabled on the text format used by that field.
2. The filter runs **before** "Limit allowed HTML tags" and "Convert line
   breaks", otherwise the `<ace>` tag is stripped or its newlines mangled. Give
   the Ace Filter a low (negative) weight on the format's filter order.
3. The Ace library is installed.

See [Embedding Snippets (Filter)](1-users/3-filter.md).

## Can a single snippet use a different theme or syntax?

Yes. Add attributes to the tag, e.g.
`<ace theme="twilight" syntax="javascript" print-margin="1">…</ace>`. These
override the filter defaults for that snippet only.

## Does the editor keep my content if JavaScript is disabled?

Yes. The original `<textarea>` is only hidden, never removed, and the editor
writes back to it on every change, so the form still submits the field value.

## Is the rendered code editable on the page?

No. The field formatter and the text filter both render **read-only** editors —
they are for display only. Only the text-editor integration on edit forms is
editable.

## I see 404 errors for mode, theme or worker files

Ace loads its mode, theme and worker files on demand, and resolves them against
the URL of its own script. When that URL is not the library directory - an
aggregate, or an asset served from elsewhere - those requests go to the wrong
place.

Two things keep the path right: the Ace builds are excluded from aggregation, so
the script keeps its own URL, and the module publishes the library directory in
`drupalSettings.ace_editor.base_path`, which `js/setup.js` applies to
`ace.config`. Rebuild the cache after updating.

## Does it work with Drupal 10, 11 and 12?

Yes — the module requires Drupal core `^10 || ^11 || ^12`.
