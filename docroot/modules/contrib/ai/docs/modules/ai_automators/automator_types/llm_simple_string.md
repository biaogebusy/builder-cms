# LLM: Text (simple)

## Field it applies to

- **Field type:** `string`
- **Potential target:** (none)

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmSimpleString.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmSimpleString.php?ref_type=heads)

## Description

The **LLM: Text (simple)** rule is designed for plain string fields.
It uses a simple language model chat interaction to generate or process short text values, returning raw output without post-processing except optional code block extraction.

## Requirements

- A configured AI chat provider (e.g., OpenAI, Azure)

## Form fields required

- `Prompt` or `Token` input
- AI model selection
- **Code block extraction type:** (Optional) Extracts a specific code block type from the LLM output (e.g., HTML, JSON)

## Example use cases

1. Auto-generate concise titles or labels.
2. Provide short summaries or tags.
3. Populate single-line field content based on context.
4. Extract a specific code snippet into a plain text field.

---

*This documentation was AI-generated.*
