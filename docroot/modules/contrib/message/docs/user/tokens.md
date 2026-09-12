# Tokens and message text

Message text can combine three kinds of placeholders. Understanding when each
is replaced helps you choose the right approach for activity streams.

## Dynamic tokens

Dynamic tokens use the usual Drupal token syntax, for example
`[current-date:short]` or `[message:uid:entity:name]`.

They are replaced **when the message is displayed**, so the output can change if
the underlying data changes (unless you clear unused tokens or the data is
gone).

Enable the Token module for more tokens and a browser on the template form.
Field values on the message are available through the token system as well, for
example `[message:field_node_reference:entity:title]`.

Token replacement on display is controlled by the template's **token options**
(token replace / clear unused tokens).

## Single-use tokens

Single-use (hard-coded) tokens look like:

```text
@{message:user-name}
%{some:token}
!{some:token}
```

They are replaced **when the message is saved**. The resolved value is stored in
the message's arguments and will not update later if the referenced data
changes.

Use single-use tokens when the value should be frozen at creation time (for
example a username that you do not want to re-resolve on every display).

!!! tip
    The `message_example` template `example_user_register` uses
    `@{message:user-name}` for this pattern.

## Custom arguments

Developers can pass custom placeholders when creating a message, such as
`@some_text` mapped to a string or a callback. Those placeholders are replaced
on display.

Site builders usually do not configure custom arguments in the UI; see
[Arguments and callbacks](../developer/arguments-callbacks.md) for the API.

## Choosing an approach

| Approach | When replaced | Updates later? |
|----------|---------------|----------------|
| Dynamic `[token]` | Display | Yes (while data exists) |
| Single-use `@{token}` | Save | No |
| Custom `@argument` | Display | Depends on stored value / callback |
