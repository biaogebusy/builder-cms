# LLM: Text (simple)

## Field it applies to

- **Field type:** `text_with_summary`
- **Potential target:** (none)

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmSimpleTextWithSummary.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmSimpleTextWithSummary.php?ref_type=heads)

## Description

The **LLM: Text (simple)** rule uses a simple text-to-text language model to generate or transform text.
It returns raw output or optionally extracts code blocks (e.g. HTML) from the model's response.
This rule can apply a text format to the generated content for use in fields with summary and text.

## Requirements

- A configured AI chat provider (e.g., OpenAI, Azure, etc.)

## Form fields required

- `Prompt` or `Token` (depending on configuration)
- AI model selection
- **Text format:** Optional selection of a text format for storing content
- **Code block extraction type:** Optional selector for code block type to extract (e.g., HTML, JSON, PHP)

## Example use cases

1. Automatically generate summaries for body fields.
2. Rewrite or simplify user-submitted text.
3. Create templated content for blog posts or news articles.
4. Generate formatted HTML for CKEditor use.
5. Extract specific code block formats (e.g., HTML snippets or JSON) from LLM responses.
6. Build intros or conclusions for articles.
7. Auto-generate teaser text or excerpts.
8. Enhance text with additional formatting or structure.

---

*This documentation was AI-generated.*
