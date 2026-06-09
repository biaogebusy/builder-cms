# LLM: Text

## Field it applies to

- **Field type:** `string`
- **Potential target:** N/A (plain short string)

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmString.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmString.php?ref_type=heads)

## Description

The **LLM: Text** rule generates short text content for `string` fields using a language model chat interaction.
It can combine multiple AI-generated values using a configurable joiner if needed.

## Requirements

- A configured AI chat provider (e.g., OpenAI, Azure OpenAI).

## Form fields required

- `Joiner` (to combine multiple values if configured).

## Example use cases

1. Auto-generate titles or short labels.
2. Populate summary fields or short descriptions.
3. Generate custom slugs or URL aliases.
4. Create image captions.
5. Fill in alt text for short image descriptions.
6. Generate short call-to-action text.
7. Auto-generate field values for tags or categories (single-word).
8. Fill in custom token values for templates.

---

*This documentation was AI-generated.*
