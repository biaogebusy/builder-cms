# LLM: Text

## Field it applies to

- **Field type:** `text`
- **Potential target:** *none*

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmText.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmText.php?ref_type=heads)

## Description

The **LLM: Text** rule generates plain text for text fields using an AI chat model.
It outputs values as RFC8259-compliant JSON for structured storage.
Supports automatic text format selection and value joining when multiple prompts are configured.

## Requirements

- A configured AI chat provider (e.g., OpenAI, Azure OpenAI).
- Text format must be selected or determinable by user rights, especially for cron jobs.

## Form fields required

- **Use text format**
  Allows the administrator to select a specific text format for output.
  Essential for cron jobs where the anonymous user’s permissions apply.

- **Joiner** (via advanced options)
  Lets you specify how multiple generated text values should be combined (e.g., newline, space, custom delimiter).

## Example use cases

1. Generate short summaries or descriptions for content fields.
2. Create intro blurbs for product pages.
3. Auto-fill metadata fields with descriptive text.
4. Draft short announcements or updates.
5. Compose callout text for feature sections.
6. Create instructional labels for UI elements.
7. Generate placeholder text for draft nodes.
8. Provide AI-generated responses in user-submitted forms.

---

*This documentation was AI-generated.*
