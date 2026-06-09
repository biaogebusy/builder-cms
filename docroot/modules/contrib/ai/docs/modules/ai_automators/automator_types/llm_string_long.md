# LLM: Text

## Field it applies to

- **Field type:** `string_long`
- **Potential target:** N/A (plain long string)

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmStringLong.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmStringLong.php?ref_type=heads)

## Description

The **LLM: Text** rule generates text content for `string_long` fields using a language model chat interaction.
It supports joining multiple AI-generated values using a configurable joiner.

## Requirements

- A configured AI chat provider (e.g., OpenAI, Azure OpenAI).

## Form fields required

- `Joiner` (to combine multiple values if applicable).

## Example use cases

1. Auto-generate product descriptions.
2. Fill in teaser texts for content.
3. Generate summaries for reports.
4. Populate metadata fields with descriptive text.
5. Create taglines or slogans based on other field data.
6. Generate image alt text for accessibility.
7. Fill in long plain-text notes fields.
8. Auto-generate placeholders or boilerplate content for editors.

---

*This documentation was AI-generated.*
