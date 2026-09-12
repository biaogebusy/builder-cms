# Message example module

The `message_example` submodule demonstrates templates, fields, tokens, and
entity hooks that create and update messages.

## Enabling

```bash
drush en message_example -y
```

Dependencies include Message, Token, Node, and Comment.

## Shipped templates

Optional config installs three templates:

| Template ID | Created when |
|-------------|----------------|
| `example_create_node` | A node is inserted |
| `example_create_comment` | A comment is inserted |
| `example_user_register` | A user is inserted |

### Notable patterns

- **Dynamic tokens** — `example_create_node` uses tokens such as
  `[message:user-name]`, field tokens, and `[message:node-render]`.
- **Single-use tokens** — `example_user_register` uses
  `@{message:user-name}` so the name is frozen at registration time.
- **Fields** — `field_node_reference`, `field_comment_reference`, and
  `field_published` support references and published-state filtering.

## Hooks

In `message_example.module`:

- `hook_node_insert()` / `hook_comment_insert()` / `hook_user_insert()` create
  messages
- `hook_node_update()` / `hook_comment_update()` sync `field_published` on
  related messages when publish status changes

## Custom tokens

`message_example.tokens.inc` defines message tokens including `user-name`,
`user-url`, `node-render`, `node-title`, `node-url`, and `comment-url`. See
[Custom tokens](custom-tokens.md).

## Trying it out

1. Enable the module and its dependencies
2. Create users, nodes, and comments
3. Inspect created messages at `/admin/content/message` (requires Views and the
   overview permission)
4. Unpublish a node or comment and confirm related messages update
   `field_published`

!!! note
    Older README text for this submodule mentioned a dedicated
    `message-example` path and Panels row plugins. Those are not shipped with
    the current submodule. Rely on the admin Messages view or your own View for
    display.
