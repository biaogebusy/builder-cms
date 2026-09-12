# Ace Editor Documentation

Integrates the [Ace code editor](https://ace.c9.io/) into Drupal so you can edit
and display raw HTML, PHP, JavaScript, CSS, YAML and many more languages with
syntax highlighting, proper indentation, keyboard shortcuts and find & replace.

## What is Ace Editor?

Ace Editor surfaces the Ace code editor in three independent ways:

- **Text editor** — attach Ace to any text format so node, block and other edit
  forms get a full code editor in place of the plain `<textarea>`.
- **Field formatter** — display a long-text field as read-only, syntax-highlighted
  code with the *Ace Format* formatter.
- **Text filter** — embed code snippets in body text with `<ace> … </ace>` tags;
  the *Ace Filter* renders each snippet as a read-only highlighted editor.
- **Field widget** — edit a plain long-text field with the *Ace Editor* widget,
  for fields that carry no text format.

They all share a common configuration (theme, syntax mode, dimensions, font
size, line numbers, print margin, word wrap, autocomplete).

### Key Features

- **Many themes and syntaxes** — every Ace theme and language mode bundled with
  the library is auto-discovered and selectable.
- **Three integration points** — editor, formatter and filter, used together or
  on their own.
- **Per-snippet overrides** — `<ace>` tags accept attributes to override the
  theme, syntax and other options for a single snippet.
- **Library auto-detection** — the Ace library is located automatically under
  `/libraries`.

## Getting Started

### For Content Editors

- [Installation and Setup](1-users/0-installation.md) — download the Ace library and enable the module
- [Using the Code Editor](1-users/1-using-the-editor.md) — edit fields with the Ace editor
- [Displaying Code (Formatter)](1-users/2-formatter.md) — render a field as highlighted, read-only code
- [Embedding Snippets (Filter)](1-users/3-filter.md) — drop `<ace>` snippets into body text

### For Site Administrators

- [Configuration](2-admins/0-configuration.md) — configure the editor, formatter and filter
- [Themes and Syntaxes](2-admins/1-themes-and-syntaxes.md) — the available themes and language modes
- [Permissions](2-admins/2-permissions.md) — the module's permission

### For Developers

- [Architecture](3-developers/0-architecture.md) — the plugins, libraries and JavaScript behaviours
- [Library Detection](3-developers/1-library-detection.md) — how the Ace library path is resolved

## Quick Links

- [FAQ](faq.md) — frequently asked questions
- [Project Page](https://www.drupal.org/project/ace_editor) — Drupal.org project page
- [Issue Queue](https://www.drupal.org/project/issues/ace_editor) — report bugs and request features
- [Ace Editor library](https://github.com/ajaxorg/ace-builds) — the upstream Ace builds

## Need Help?

- Check the [FAQ](faq.md) for common questions.
- Review the relevant documentation section based on your role.
- Visit the [issue queue](https://www.drupal.org/project/issues/ace_editor) for support.
