# Embedding Snippets (Filter)

The **Ace Filter** turns `<ace> … </ace>` tags inside body text into read-only,
syntax-highlighted Ace editors. Use it to drop code snippets into otherwise
normal content.

## Enable the filter

1. Go to **Configuration → Content authoring → Text formats and editors**
   (`/admin/config/content/formats`).
2. Edit the text format your content uses.
3. Enable **Ace Filter** under **Enabled filters**.
4. **Filter processing order matters.** Drag *Ace Filter* so it runs **before**
   *Limit allowed HTML tags* and *Convert line breaks*. If it runs after them,
   the `<ace>` tag is stripped or the snippet's line breaks are mangled. On a
   format without those filters (e.g. *Full HTML*) ordering is less critical, but
   running the Ace Filter first is always safe.
5. Set the default theme/syntax/size under the filter's settings.
6. Save.

## Write a snippet

```html
<ace>function hello(name) {
  return "Hello, " + name;
}</ace>
```

## Override options per snippet

Attributes on the `<ace>` tag override the filter defaults for that snippet only:

```html
<ace theme="twilight" syntax="javascript" print-margin="1" height="200px">
function hello(name) {
  return "Hello, " + name;
}
</ace>
```

Recognised attributes mirror the configuration options: `theme`, `syntax`,
`height`, `width`, `font-size`, `line-numbers`, `print-margin`,
`show-invisibles`, `use-wrap-mode`. Hyphenated attribute names map to the
underlying settings (e.g. `print-margin` → `print_margins`).

## Result

Each `<ace>` block is replaced by a read-only Ace editor showing the snippet with
the chosen (or default) theme and language highlighting. Multiple snippets per
page are supported, each with its own attributes.
