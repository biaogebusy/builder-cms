# Listing messages with Views

Message ships with Views integration so you can list and filter message
entities.

## Admin messages view

When Views is enabled, Message provides an optional view at:

**Content → Messages** (`/admin/content/message`)

Access requires the **Overview messages** permission.

This view is a starting point for administrative review and bulk operations
(including delete). Customize it like any other View, or clone it for public
activity streams.

## Building your own View

1. Create a View of **Message** entities.
2. Add fields such as author, created time, template, and custom fields on the
   template.
3. Use the **Message text** field (`get_text`) to render the processed message
   text. You can limit output to a single partial delta when needed.
4. Choose a view mode if you want Manage display settings (including partial
   visibility) to apply.

## Tips for activity streams

- Add contextual or exposed filters by template, author, or referenced entity
- Use fields such as a published flag (as in `message_example`) to hide messages
  when related content is unpublished
- Theme the message entity or use Twig suggestions for per-template markup; see
  [Rendering and theming](../developer/rendering-theming.md)
