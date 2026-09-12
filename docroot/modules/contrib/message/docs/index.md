# Message

Message is a general logging utility for Drupal. It provides a **message**
content entity and configurable **message templates** so you can record system
events—such as content creation, comments, or user registration—and display
them as activity streams or elsewhere on the site.

## Documentation

| Section | Audience |
|---------|----------|
| [User guide](user/overview.md) | Site builders and administrators configuring templates, tokens, Views, and purge |
| [Developer guide](developer/architecture.md) | Module developers creating messages in code, tokens, purge plugins, and migrations |

## Related projects

Message is the core of a broader stack:

- [Message Notify](https://www.drupal.org/project/message_notify) — send messages via notifier plugins (for example email or SMS)
- [Message Subscribe](https://www.drupal.org/project/message_subscribe) — notify users who subscribe to content (typically with Flag)

These modules are optional and not required to use Message itself.

## Requirements

- Drupal 9.2+, 10, 11, or 12
- The core Text module

Enabling the [Token](https://www.drupal.org/project/token) module is
recommended. It provides additional tokens and a token browser on message
template forms.
