# Input Length Limit Guardrail

The **Input Length Limit** guardrail (`input_length_limit`) blocks inputs that exceed a configurable length limit. It supports both character-based and token-based counting, and can evaluate either the last user message or the entire conversation history.

## When to Use

Use this guardrail to:
- Prevent users from sending excessively long prompts that could result in high API costs.
- Avoid exceeding model context windows or token limits.
- Enforce strict input limits on chat forms.

## Configurable Fields

| Field | Key | Type | Default | Description |
|-------|-----|------|---------|-------------|
| **Maximum length** | `max_length` | Number | `5000` | The maximum allowed length. Interpreted as characters or tokens depending on the counting method. |
| **Use token-based counting** | `use_tokens` | Checkbox | `false` | When enabled, the limit is applied to the number of tokens instead of characters. Uses the `ai.tokenizer` service. |
| **Tokenizer model** | `tokenizer_model` | Textfield | `gpt-4` | The model to use for token counting (e.g., `gpt-4`, `gpt-3.5-turbo`). Only visible when token-based counting is enabled. |
| **Check total conversation length** | `check_all_messages` | Checkbox | `false` | When enabled, the limit applies to all messages in the conversation combined instead of just the last user message. |
| **Violation message** | `violation_message` | Textarea | *See below* | The message displayed when the limit is exceeded. Supports placeholders: `@count` (actual length), `@max` (configured limit), `@unit` (characters/tokens). |

### Default Violation Message

```text
Your input has @count @unit, which exceeds the maximum of @max @unit.
```

## Example Configuration

Below is an example configuration for enforcing a 1,000-token limit on the total conversation history using the GPT-4 tokenizer:

```yaml
id: input_length_limit
max_length: 1000
use_tokens: true
tokenizer_model: gpt-4
check_all_messages: true
violation_message: "The chat history is too long (@count @unit). Please start a new session or keep it under @max @unit."
```
