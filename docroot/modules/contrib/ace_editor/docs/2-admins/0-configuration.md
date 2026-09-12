# Configuration

All three integrations (editor, formatter, filter) share the same set of options.
Defaults live in the `ace_editor.settings` configuration object and are used as the
starting point for each integration's own settings form.

## Options

| Option | Key | Description |
| --- | --- | --- |
| Theme | `theme` | The Ace colour theme (e.g. *cobalt*, *twilight*, *github*). |
| Syntax | `syntax` | The language mode used for highlighting (e.g. *html*, *php*, *css*). |
| Height | `height` | Editor height, in pixels or percent (e.g. `300px`), or `auto` to fit the content. |
| Width | `width` | Editor width, in pixels or percent (e.g. `100%`). |
| Font size | `font_size` | Editor font size (e.g. `12pt`). |
| Show line numbers | `line_numbers` | Toggle the line-number gutter. |
| Show print margin | `print_margins` | Toggle the 80-column print-margin guide. |
| Show invisible characters | `show_invisibles` | Toggle whitespace / EOL markers. |
| Toggle word wrapping | `use_wrap_mode` | Wrap long lines instead of scrolling. |
| Enable Autocomplete | `auto_complete` | `Ctrl+Space` basic autocompletion (editor only). |

## Where each integration is configured

- **Editor** — *Configuration → Content authoring → Text formats and editors*,
  on the format that uses **Ace Editor** as its text editor. See
  [Using the Code Editor](../1-users/1-using-the-editor.md).
- **Formatter** — the field's **Manage display** screen, under the **Ace Format**
  formatter settings. See [Displaying Code (Formatter)](../1-users/2-formatter.md).
- **Filter** — the **Ace Filter** settings on a text format, plus optional
  per-snippet `<ace>` attributes. See
  [Embedding Snippets (Filter)](../1-users/3-filter.md).

## Default settings

The shipped defaults are theme *cobalt*, syntax *html*, height `300px`, width
`100%`, font size `12pt`, line numbers on, print margin on, invisibles off, word
wrap on, autocomplete on. They can be overridden per integration (and, for the
filter, per `<ace>` tag).

## A note on the print-margin key

The configuration/UI key is `print_margins` (plural). Older inline `<ace>` tags
may use the `print-margin` attribute; both are honoured.
