# Using the Code Editor

The text-editor integration replaces the plain `<textarea>` on edit forms with a
full Ace code editor for any text format you assign it to.

## Enable Ace as a format's editor

1. Go to **Configuration → Content authoring → Text formats and editors**
   (`/admin/config/content/formats`).
2. Edit (or add) a text format — for example *Full HTML*.
3. Under **Text editor**, choose **Ace Editor**.
4. Configure the **Ace Editor Settings** that appear (theme, syntax, height,
   width, font size, line numbers, print margin, show invisibles, word wrap,
   autocomplete). See [Configuration](../2-admins/0-configuration.md).
5. Save the format.

Any field that offers this text format now shows the Ace editor when that format
is selected.

## Fields without a text format

A plain long text field has no text format, so a format's editor cannot reach it.
Use the **Ace Editor** field widget instead:

1. Go to the bundle's **Manage form display**.
2. Set the field's widget to **Ace Editor**.
3. Configure the same settings on the widget itself, through its gear icon.

The widget carries the settings of each field, so two fields on one form can use
different themes and syntaxes.

## What you get

- Syntax highlighting for the configured language.
- Line numbers, an optional 80-column print margin, optional whitespace markers.
- Optional word wrapping and `Ctrl+Space` autocomplete.
- Standard Ace keyboard shortcuts (find/replace, multi-cursor, indentation).

## How your content is saved

The original `<textarea>` is hidden — not removed. Every change in the Ace editor
is written back to it, so the form submits exactly what you typed even though the
textarea isn't visible. Fields marked **required** stay required: the constraint
is restored when the editor is detached.

## Tips

- The editor is best for raw markup/code formats (Full HTML, or a dedicated
  "Code" format). Pair it with the *Ace Format*
  [field formatter](2-formatter.md) to display the saved code with the same
  highlighting on the rendered page.
- Choose the **syntax** that matches the content you edit (HTML, PHP, CSS, YAML,
  JavaScript, …). The full list is in
  [Themes and Syntaxes](../2-admins/1-themes-and-syntaxes.md).
