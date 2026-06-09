# LLM: Text (simple)

## Field it applies to

- **Field type:** `string_long`
- **Potential target:** (none)

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmSimpleStringLong.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmSimpleStringLong.php?ref_type=heads)

## Description

The **LLM: Text (simple)** rule generates or transforms longer plain text strings using a simple language model chat interface.
It provides raw output from the LLM and can optionally extract specific code block types from the response.

## Requirements

- A configured AI chat provider (e.g., OpenAI, Azure)

## Form fields required

- `Prompt` or `Token` (depending on configuration)
- AI model selection
- **Code block extraction type:** (Optional) Choose a code block type to extract (e.g., HTML, JSON, PHP)

## Example use cases

1. Generate product descriptions.
2. Populate long titles or subtitles.
3. Create extended labels or metadata.
4. Auto-fill long string fields based on context.
5. Extract and store a specific code block type from the LLM output.

---

*This documentation was AI-generated.*
