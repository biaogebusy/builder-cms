# Function Call Schema in AI Module

The AI module provides a robust mechanism for defining function call schemas that LLMs can use to interact with your code. This document explains the schema options available for more precise value constraints.

## Constraints

When defining function call parameters through context definitions, you can use the following constraints:

### Constant Values

Use the `FixedValue` constraint when a parameter must have a specific value that the LLM cannot change. This is useful for enforcing specific identifiers or settings.

```php
'entity_type' => new ContextDefinition(
  data_type: 'string',
  label: 'Entity Type',
  description: 'The entity type to interact with.',
  required: TRUE,
  constraints: [
    'FixedValue' => 'node',
  ],
),
```

The constraint will be translated to a `const` property in the JSON schema, indicating to the LLM that this value is fixed and cannot be changed.

### Allowed Values

For parameters that must be one of a predefined set of values, use the `Choice` constraints.

```php
'bundle' => new ContextDefinition(
  data_type: 'string',
  label: 'Content Type',
  description: 'The content type to use.',
  required: TRUE,
  constraints: [
    'Choice' => ['article', 'page', 'blog'],
  ],
),
```

These constraints are rendered as an `enum` in the JSON schema, providing the LLM with a list of valid options.

### Length Constraints

For string parameters where you need to enforce minimum and/or maximum length, use the `Length` constraint.

```php
'title' => new ContextDefinition(
  data_type: 'string',
  label: 'Title',
  description: 'The title of the content item.',
  required: TRUE,
  constraints: [
    'Length' => [
      'min' => 3,
      'max' => 255,
    ],
  ],
),
```

This ensures the LLM generates strings within the specified length range.

### Numeric Range

For numeric parameters where values must fall within specific bounds, use the `Range` constraint.

```php
'weight' => new ContextDefinition(
  data_type: 'integer',
  label: 'Weight',
  description: 'The weight determining the order of the item (lower values appear first).',
  required: TRUE,
  constraints: [
    'Range' => [
      'min' => -100,
      'max' => 100,
    ],
  ],
),
```

This ensures the LLM generates numeric values within the specified range.

## Example Plugin with Constraints

Here's a complete example of a function call plugin using various constraints:

```php
#[FunctionCall(
  id: 'my_module:create_content',
  function_name: 'create_content',
  name: 'Create Content',
  description: 'Creates a new content item with specified parameters.',
  group: 'content_management',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: 'Entity Type',
      description: 'The entity type (always "node").',
      required: TRUE,
      constraints: [
        'FixedValue' => 'node',
      ],
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: 'Content Type',
      description: 'The content type to create.',
      required: TRUE,
      constraints: [
        'Choice' => ['article', 'page', 'blog'],
      ],
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: 'Title',
      description: 'The title of the content.',
      required: TRUE,
      constraints: [
        'Length' => ['min' => 3, 'max' => 255],
      ],
    ),
    'status' => new ContextDefinition(
      data_type: 'boolean',
      label: 'Published',
      description: 'Whether the content should be published.',
      required: FALSE,
      default_value: TRUE,
    ),
  ],
)]
```

By using these constraints, you ensure that LLMs provide more accurate and valid parameters to your function calls, reducing errors and improving reliability.
