# JSON Schema Editor

## What is the JSON Schema Editor?

The `ai_json_schema` form element provides a CodeMirror 6–based JSON editor
with syntax highlighting, line numbers, and real-time JSON linting. It is
designed for editing JSON schemas or any structured JSON data within Drupal
forms.

## How can I use the editor?

Use the `ai_json_schema` form element type in any Drupal form:

```php
$form['json_schema'] = [
  '#type' => 'ai_json_schema',
  '#title' => $this->t('JSON Schema'),
  '#description' => $this->t('Enter a valid JSON schema.'),
  '#default_value' => '{"type": "object", "properties": {}}',
];
```

The element renders a hidden `<input>` that carries the form value, a fallback
`<textarea>` for when JavaScript is unavailable, and a CodeMirror editor that
takes over when JS loads. The default value is automatically pretty-printed in
the editor.

## Graceful fallback

If JavaScript fails to load, the user still sees a plain `<textarea>` they can
type into. When CodeMirror initializes successfully it hides the fallback
textarea, shows the rich editor, and syncs all changes back to the hidden input
for normal form submission.

## Why is the built editor not included?

The built assets are only included for tagged versions of the module. If you use
the branch for development you need to build the assets yourself. For this you
will need to have `npm` running locally. The minimum required version of `node`
is `20.14.0`.

To build the editor assets follow the instructions:

1. Go to `ui/json-schema-editor` folder.
2. Run `npm install`
3. Run `npm run build`
4. Do not commit the changes from `ui/json-schema-editor/dist` folder.
