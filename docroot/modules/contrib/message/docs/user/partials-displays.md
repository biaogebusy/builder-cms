# Partials and displays

## Partials

The message body on a template has multiple cardinality. Each delta is a
**partial**—a separate piece of text that can be shown, hidden, or reordered
independently.

Typical uses:

- Keep markup in one partial and the main sentence in another
- Show a short summary in a teaser view mode and a fuller body in the full view
  mode
- Render only selected partials in Views

## Manage display

Message exposes each partial as an extra field named `partial_0`, `partial_1`,
and so on. Configure them on the template's **Manage display** page, for
example:

`/admin/structure/message/manage/{template}/display`

There you can:

- Show or hide individual partials per view mode
- Reorder partials and other fields
- Control how related fields (references, author, created time) appear

A default **full** view mode is provided for messages.

## Views and partials

When building a View of messages, you can use the **Message text** field plugin
to output rendered text, optionally for a specific delta. Combine that with
Manage display settings for the view mode you select in the View.
