# Chat History form element

The `chat_history` form element renders and manages a list of chat messages (user, assistant, tool) with support for adding/removing messages, re-ordering drag and drop, and handling assistant tool calls and tool message references.

## Usage

Add the element to any Drupal form:

```php
$form['chat'] = [
  '#type' => 'chat_history',
  '#title' => t('Chat history'),
  '#default_value' => [
    [
      'role' => 'user',
      'content' => 'Hello',
    ],
    [
      'role' => 'assistant',
      'content' => 'Hi! How can I help?',
      'tool_calls' => [
        'tool_call_id' => 'call-1',
        'function_name' => 'lookup_weather',
        'function_input' => '{"city":"Oslo"}',
      ],
    ],
  ],
];
```

### Additional example

Include a tool response that references a previous assistant tool call and stores a result:

```php
$form['chat'] = [
  '#type' => 'chat_history',
  '#title' => t('Chat history'),
  '#default_value' => [
    [
      'role' => 'assistant',
      'content' => 'Let me look that up…',
      'tool_calls' => [
        'tool_call_id' => 'call-1',
        'function_name' => 'lookup_weather',
        'function_input' => '{"city":"Oslo"}',
      ],
    ],
    [
      'role' => 'tool',
      'content' => 'Partly cloudy, 22°C',
      'tool_call_id_reference' => 'call-1',
    ],
  ],
];
```

## Message structure

Each message is an associative array with fields:

- `role` (string): `user` | `assistant` | `tool`
- `content` (string): message text

### Assistant tool calls

Assistant messages can include a `tool_calls` array. Each tool call has:

- `tool_call_id` (string)
- `function_name` (string)
- `function_input` (string)

The UI provides Add/Remove controls for tool calls and rebuilds via AJAX.

### Tool message reference

Tool messages can include a `tool_call_id_reference` field that links back to a previous assistant tool call ID.
