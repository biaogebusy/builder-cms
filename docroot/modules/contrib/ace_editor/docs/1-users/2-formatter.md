# Displaying Code (Formatter)

The **Ace Format** field formatter renders a long-text field as a read-only,
syntax-highlighted Ace editor on the rendered entity. It is display-only — the
code cannot be edited on the page.

## Supported field types

- `text_long`
- `text_with_summary`

## Configure the formatter

1. Go to the entity's **Manage display** screen — for example
   **Structure → Content types → Article → Manage display**.
2. For the long-text field (e.g. *Body*), set the **Format** to **Ace Format**.
3. Click the gear icon to set the theme, syntax, height, width, font size, line
   numbers, print margin, show invisibles and word wrap.
4. Save.

## Result

When the entity is viewed, the field value is shown inside a read-only Ace editor
with the configured theme and language highlighting. The underlying markup is kept
in a hidden textarea and rendered by Ace, so visitors see neatly formatted code
rather than raw text or parsed HTML.

## When to use the formatter vs. the filter

- Use the **formatter** when an entire field holds code/markup to display as code.
- Use the [filter](3-filter.md) when you want to embed one or more code snippets
  inside otherwise normal body text using `<ace>` tags.
