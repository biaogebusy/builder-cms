# Linkable Provider Configuration Schema

## What is it?

A standardized schema for storing AI provider configurations in third-party modules. This ensures consistency across modules and enables future features like failover support.

## Schema Fields

The schema `ai.provider_config` has four fields:

**use_default** (boolean) - Whether to use the system's default provider for the operation type.

**provider_id** (string) - The plugin ID of the AI provider (e.g., `openai`, `anthropic`).

**model_id** (string) - The model identifier (e.g., `gpt-4`, `claude-3-opus`).

**configuration** (sequence) - Provider-specific configuration parameters as key-value pairs (e.g., temperature, max_tokens).

## How to use it

### In your module schema

```yaml
my_module.settings:
  type: config_object
  label: 'My Module Settings'
  mapping:
    chat_provider:
      type: ai.provider_config
      label: 'Chat Provider Configuration'
```

### In your configuration

```yaml
# Use default provider
chat_provider:
  use_default: true

# Use specific provider
embeddings_provider:
  use_default: false
  provider_id: 'openai'
  model_id: 'text-embedding-3-small'
  configuration:
    dimensions: 1536
```
