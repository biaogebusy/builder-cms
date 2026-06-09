# LLM: Metatag

## Field it applies to

- **Field type:** `metatag`
- **Target field name:** Any metatag field.

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmMetatag.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmMetatag.php?ref_type=heads)

## Description

The **LLM: Metatag** rule uses a large language model (LLM) to automatically generate values for various metatag fields based on the content's context.
It helps improve SEO and sharing by filling out relevant metadata.

## Requirements

- The Metatag module must be installed and enabled.
- A configured AI provider (e.g., OpenAI).

## Form configuration options

For each metatag field:

- **Setup** — Define instructions for generating this tag's value.
- **Example** — Provide an example output for this tag to guide the AI.

Each metatag group and tag can be individually configured or skipped (by leaving it empty).

## Example use cases

- Automatically generate page descriptions, titles, or Twitter cards based on body content.
- Create dynamic Open Graph tags for social sharing.
- Provide consistent and optimized metadata without manual input.

## Notes

- The AI generates a JSON structure containing the metatag values, which is saved directly into the field.
- You can configure which tags are generated and provide examples to improve output quality.
- Only one metatag value set is stored per field.

---

*This documentation was AI-generated.*
