# Overview

Message records events as entities you can list, theme, and purge. It is
commonly used for activity streams and as the foundation for notification
workflows when combined with Message Notify or Message Subscribe.

## Core concepts

### Message template

A **message template** is a configuration entity. It defines:

- Human-readable label and description
- One or more text **partials** (the message body deltas)
- Token replacement options
- Optional purge settings that override the global configuration

Templates live under **Structure → Message templates**
(`/admin/structure/message`). They are exportable with Drupal's configuration
management.

Each template is also the **bundle** for message entities. You can attach fields
to a template (for example an entity reference to a node) using Field UI when
that module is enabled.

### Message

A **message** is a content entity instance of a template. It stores:

- Which template it uses
- The author (`uid`) and timestamps
- **Arguments** used when rendering placeholders
- Any fields attached to that template

Messages are created when your site (or custom code) logs an event. Site
builders typically define templates in the UI; developers create message
entities in code when events occur. See the
[developer guide](../developer/creating-messages.md) for the API.

## Common use cases

1. **Logging and displaying system events** — Record that a node was created, a
   comment was posted, or a user registered, then show those events in a feed.
2. **Notifying users** — Pair Message with Message Notify to deliver message
   text through email or other channels.
3. **Subscription-based alerts** — Pair Message with Message Subscribe so users
   who flag or subscribe to content receive related messages.

## What to configure next

1. [Install and configure](install-configure.md) global settings
2. [Create message templates](templates.md)
3. Learn how [tokens and message text](tokens.md) work
4. Optionally configure [purging](purge.md) so old messages are cleaned up
